# Dyno Focus · AI Vice CEO — Phase 1 Audit & Implementation Plan

## 1. Existing application audit

| Item | Finding |
| --- | --- |
| Repository | `Dynopos/Ai-focus` was **empty** (no branches, no commits) when the upgrade started. No other repository in the account was identified as the existing Dyno Focus app. |
| Decision | With the owner's confirmation, Dyno Focus was **built fresh in this repository** on the recommended stack (Laravel), rather than "upgraded". Nothing existing could be overwritten. |
| Framework | Laravel 13 (PHP 8.3), Blade views, plain CSS (no Node build step required). |
| Database | SQLite by default (MySQL/PostgreSQL supported via `DB_*`). Migrations in `database/migrations`. |
| Auth | Session login + self-registration (creates a business and its CEO). Roles: `ceo`, `manager`, `staff`. |
| AI | Claude via the official Anthropic PHP SDK (`anthropic-ai/sdk`). Optional: without a key, deterministic rules are used. |
| Branding | Logo supplied by the CEO (`public/images/dyno-focus-logo.*`). Palette taken from it: chrome/silver, red `#d7141a`, black. |

## 2. Architecture

```
            ┌───────────── Channels ─────────────┐
 Dashboard ─┤  Email (Laravel Mail, any mailer)   ├──┐
 WhatsApp  ─┤  Meta Cloud API (webhook + send)    │  │ every write goes through
            └─────────────────────────────────────┘  ▼
                                   TaskService ── one shared task DB + activity history
   CeoBrain (Level 1: analyse, prioritise, report, draft, recommend)
        │ proposes
        ▼
   ApprovalService  ── Decision Room (Level 2 / Level 3), version + hash bound, single-use
        │ approved (Level 2 only)
        ▼
   ActionExecutor ── CommunicationService (idempotent, logged, retried) ── WhatsAppGateway (Meta | Log)
   AutomationService + Scheduler + Queue ── briefings, reminders (timezone-aware, once per day)
```

Key files

| Module | Code |
| --- | --- |
| 1 CEO Brain | `app/Services/Executive/CeoBrain.php`, `PriorityEngine.php`, `FollowUpDrafter.php` |
| 2 Shared tasks | `app/Models/Task.php`, `TaskActivity.php`, `app/Services/Executive/TaskService.php` |
| 3 Email | `app/Services/Executive/ReportBuilder.php`, `resources/views/emails/*`, `app/Mail/HtmlMessage.php` |
| 4 WhatsApp | `app/Services/Messaging/*Gateway.php`, `app/Http/Controllers/Webhooks/WhatsAppWebhookController.php`, `app/Services/WhatsApp/*` |
| 5 Approvals | `app/Enums/ActionType.php` (authority levels), `app/Services/Executive/ApprovalService.php`, `ActionExecutor.php` |
| 6 Dashboard | `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php` |
| 7 Automation | `app/Services/Executive/AutomationService.php`, `app/Jobs/*`, `routes/console.php` |
| 8 Memory | `app/Models/Memory.php`, `app/Http/Controllers/MemoryController.php` |

## 3. Data model

`businesses` (tenant) · `users` (+business, role, verified WhatsApp phone) · `projects` · `contacts` (customers, WhatsApp opt-in) · `tasks` (all required fields: business/project, title, description, priority, assignee, deadline, status, revenue potential, financial impact, source, created/updated, completion evidence, CEO approval status) · `task_activities` · `approvals` · `audit_logs` · `communication_logs` · `webhook_events` · `automations` · `automation_runs` · `memories`.

Task statuses: Pending, In Progress, Waiting for CEO, Waiting for Customer, Completed, Cancelled.

## 4. Authority levels

| Level | What | Enforcement |
| --- | --- | --- |
| 1 Autonomous | Analyse, prioritise, report, draft, recommend, internal reminders | No approval needed |
| 2 CEO approval | Customer WhatsApp/email, important reassignments, sensitive record changes | `ActionType::authorityLevel()` is fixed in code; executed only after CEO approval of the exact version |
| 3 CEO only | Spending, contracts, hiring/dismissal, financial and strategic commitments | AI writes a decision brief. Approval is recorded as **acknowledged**; the executor refuses to run Level 3 |

## 5. Security considerations (implemented)

- Meta webhook: `hub.verify_token` handshake; `X-Hub-Signature-256` HMAC verified with `hash_equals`; CSRF-exempt route only; rate limited.
- Duplicate webhooks: unique `(provider, event_id)` + single-claim job.
- Only a **verified** CEO phone (proved by sending `VERIFY <code>` from that phone) of the business that owns the receiving number can command. Customers' messages are stored as data and never interpreted.
- Approvals are bound to: business, CEO user, exact version (buttons carry it, typed codes use the last version shown), payload SHA-256 hash, expiry. Single-use (atomic status claim). Forwarded messages and long/multi-line "approve" texts are refused.
- The AI fallback classifier cannot output approve/reject/modify; customer/task text is passed to the model as delimited data.
- Idempotency keys on every outbound message; retries never double-send.
- Tenant isolation via policies (`app/Policies`), scoped queries and tenant-scoped validation rules.
- Secrets only in `.env`; nothing exposed to the browser. Recurring jobs off by default (`VICE_CEO_AUTOMATIONS_ENABLED=false`); WhatsApp defaults to the mock transport.

## 6. Implementation sequence (delivered)

1. **Phase 1** – audit (this document).
2. **Phase 2** – executive core: tasks, CEO Brain, Decision Room, approval workflow, audit logs.
3. **Phase 3** – email briefings/reports, scheduler, delivery logs.
4. **Phase 4** – WhatsApp connector, webhook, command interpreter, templates, approvals over WhatsApp.
5. **Phase 5** – automated tests (`php artisan test`): task creation/status, WhatsApp commands, email reports, invalid/stale/reused approvals, duplicate webhooks, unauthorised senders, failed API responses, cross-channel sync, scheduler once-per-day/quiet hours, tenant isolation.

## 7. Acceptance criteria mapping

| # | Criterion | Covered by |
| --- | --- | --- |
| 1 | CEO asks about tasks on WhatsApp | `WhatsAppCommandTest::test_ceo_asks_for_top_priorities_from_real_tasks` |
| 2 | Real tasks from shared DB | same + `test_ceo_lists_pending_items` |
| 3 | Update status via WhatsApp | `test_ceo_marks_task_done_and_dashboard_reflects_it` |
| 4 | Dashboard reflects it | same |
| 5 | Email uses same live data | `ReportingAndSchedulingTest::test_evening_report_reflects_whatsapp_updates` |
| 6 | Approval before customer messages | `ApprovalFlowTest::test_whatsapp_draft_requires_approval_before_sending` |
| 7 | Executed once and logged | `test_approve_by_code_executes_once_and_logs` |
| 8 | Failures visible & recoverable | `test_failed_execution_is_visible_and_recoverable`, `WhatsAppProviderTest` |
| 9 | Existing features remain | No prior features existed; full page smoke test `test_dashboard_and_all_pages_render` |
| 10 | Auth & permissions | policies + `test_only_the_ceo_of_the_business_can_decide`, tenant tests |

## 8. Not yet done / next steps

- **Live credentials**: Meta WABA access token, app secret, phone number ID, approved templates; SMTP/transactional mailer; Anthropic API key. Until set, everything runs on mock/log transports.
- **Gmail OAuth** sending was not implemented; use SMTP (Gmail app password / Google Workspace relay) or a transactional provider (Resend, Postmark, SES — Laravel mailers).
- Cashflow/revenue integrations (POS, accounting) — reports currently use only revenue figures recorded on tasks and say so.
- Team management UI (inviting managers/staff) and project CRUD screens.
- Production deployment: cron for `schedule:run`, a supervised `queue:work`, HTTPS public URL for the webhook.
