<?php

namespace Tests\Feature;

use App\Enums\ActionType;
use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;
use App\Exceptions\ApprovalException;
use App\Mail\HtmlMessage;
use App\Models\Approval;
use App\Models\Business;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Executive\ApprovalService;
use App\Services\Executive\FollowUpDrafter;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsViceCeoFixtures;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use BuildsViceCeoFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
    }

    private function derolApproval(): Approval
    {
        $derol = $this->contact(['name' => 'Derol', 'company' => 'Derol Pasir Puteh', 'phone' => '60111111111', 'whatsapp_last_inbound_at' => now()]);
        $this->task(['title' => 'Hubungi Derol Pasir Puteh', 'contact_id' => $derol->id, 'revenue_potential' => 3200]);

        $this->sendWhatsApp('Sediakan follow-up Derol');

        return Approval::firstOrFail();
    }

    private function customerMessages(): array
    {
        return array_values(array_filter($this->wa->sent, fn ($m) => $m['to'] === '60111111111'));
    }

    public function test_whatsapp_draft_requires_approval_before_sending(): void
    {
        $approval = $this->derolApproval();

        $this->assertSame(ApprovalStatus::Pending, $approval->status);
        $this->assertSame(ActionType::SendCustomerWhatsApp, $approval->action_type);
        $this->assertSame(2, $approval->authority_level->value);
        $this->assertSame('60111111111', $approval->payload['phone']);
        $this->assertSame(1, $approval->notified_version);
        $this->assertSame([], $this->customerMessages(), 'Nothing may be sent to the customer before approval.');

        // CEO received the preview with buttons and a code.
        $request = collect($this->wa->sent)->firstWhere('type', 'buttons');
        $this->assertStringContainsString($approval->code, $request['body']);
        $this->assertStringContainsString('Derol Pasir Puteh', $request['body']);
    }

    public function test_approve_by_code_executes_once_and_logs(): void
    {
        $approval = $this->derolApproval();

        $this->sendWhatsApp('APPROVE '.$approval->code);

        $approval->refresh();
        $this->assertSame(ApprovalStatus::Executed, $approval->status);
        $this->assertSame($this->ceo->id, $approval->decided_by_user_id);
        $this->assertSame('whatsapp', $approval->decided_via);
        $this->assertCount(1, $this->customerMessages());
        $this->assertSame($approval->payload['message'], $this->customerMessages()[0]['body']);
        $this->assertSame(TaskStatus::WaitingForCustomer, $approval->task->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'approval.executed', 'subject_id' => $approval->id]);
        $this->assertDatabaseHas('communication_logs', ['approval_id' => $approval->id, 'type' => 'customer_message', 'status' => 'sent']);

        // Re-using the approval does nothing.
        $this->sendWhatsApp('APPROVE '.$approval->code);
        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => $approval->version])->assertSessionHasErrors('approval');
        $this->assertCount(1, $this->customerMessages());
        $this->assertStringContainsString('tidak boleh diguna semula', $this->lastWhatsAppTo('60123456789'));
    }

    public function test_approve_by_name_matches_the_single_pending_request(): void
    {
        $approval = $this->derolApproval();

        $this->sendWhatsApp('Approve follow-up Derol');

        $this->assertSame(ApprovalStatus::Executed, $approval->fresh()->status);
        $this->assertCount(1, $this->customerMessages());
    }

    public function test_button_approval_with_stale_version_is_refused(): void
    {
        $approval = $this->derolApproval();
        app(ApprovalService::class)->modify($approval, $this->ceo, ['message' => 'Edited text'], 'Edited text');

        // Button from the original (v1) notification.
        $this->sendWhatsApp('Approve', extra: [
            'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => "apv:{$approval->ulid}:1:approve", 'title' => 'Approve']],
        ]);

        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertSame([], $this->customerMessages());
        $this->assertStringContainsString('telah berubah', $this->lastWhatsAppTo('60123456789'));
    }

    public function test_typed_approval_after_modification_requires_review_of_new_version(): void
    {
        $approval = $this->derolApproval();
        app(ApprovalService::class)->modify($approval, $this->ceo, ['message' => 'Versi baharu'], 'Versi baharu');

        $this->sendWhatsApp('APPROVE '.$approval->code); // CEO has only seen v1
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);

        $this->sendWhatsApp('APPROVE '.$approval->code); // v2 was re-sent, now approve
        $this->assertSame(ApprovalStatus::Executed, $approval->fresh()->status);
        $this->assertSame('Versi baharu', $this->customerMessages()[0]['body']);
    }

    public function test_forwarded_or_long_approval_messages_are_refused(): void
    {
        $approval = $this->derolApproval();

        $this->sendWhatsApp('APPROVE '.$approval->code, extra: ['context' => ['forwarded' => true]]);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertStringContainsString('forward', $this->lastWhatsAppTo('60123456789'));

        $this->sendWhatsApp("APPROVE {$approval->code}\n> quoted text from someone else saying the CEO agreed");
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
        $this->assertSame([], $this->customerMessages());
    }

    public function test_reject_prevents_sending(): void
    {
        $approval = $this->derolApproval();

        $this->sendWhatsApp('REJECT '.$approval->code);

        $this->assertSame(ApprovalStatus::Rejected, $approval->fresh()->status);
        $this->sendWhatsApp('APPROVE '.$approval->code);
        $this->assertSame(ApprovalStatus::Rejected, $approval->fresh()->status);
        $this->assertSame([], $this->customerMessages());
    }

    public function test_expired_approval_cannot_be_approved(): void
    {
        $approval = $this->derolApproval();
        $approval->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1])->assertSessionHasErrors('approval');

        $this->assertSame(ApprovalStatus::Expired, $approval->fresh()->status);
        $this->assertSame([], $this->customerMessages());
    }

    public function test_only_the_ceo_of_the_business_can_decide(): void
    {
        $approval = $this->derolApproval();

        $staff = User::factory()->staff()->create(['business_id' => $this->business->id]);
        $this->actingAs($staff)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1])->assertForbidden();

        $otherCeo = User::factory()->create(['business_id' => Business::factory()->create()->id]);
        $this->actingAs($otherCeo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1])->assertForbidden();
        $this->actingAs($otherCeo)->get("/decision-room/{$approval->ulid}")->assertForbidden();

        $this->expectException(ApprovalException::class);
        app(ApprovalService::class)->approve($approval, $otherCeo, 1);
    }

    public function test_dashboard_approval_executes_exact_version(): void
    {
        $approval = $this->derolApproval();

        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/modify", ['message' => 'Salam Derol, versi dashboard.'])->assertSessionHasNoErrors();
        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1])->assertSessionHasErrors('approval');
        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 2])->assertSessionHasNoErrors();

        $this->assertSame(ApprovalStatus::Executed, $approval->fresh()->status);
        $this->assertSame('Salam Derol, versi dashboard.', $this->customerMessages()[0]['body']);
    }

    public function test_tampered_payload_fails_integrity_check(): void
    {
        $approval = $this->derolApproval();
        // Simulate a direct DB change of the recipient after the proposal.
        Approval::whereKey($approval->id)->update(['payload' => json_encode(['contact_id' => $approval->contact_id, 'phone' => '60999999999', 'message' => 'x'])]);

        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1])->assertSessionHasErrors('approval');
        $this->assertSame([], array_filter($this->wa->sent, fn ($m) => $m['to'] === '60999999999'));
    }

    public function test_failed_execution_is_visible_and_recoverable(): void
    {
        $derol = $this->contact(['name' => 'Derol', 'phone' => '60111111111', 'whatsapp_opt_in_at' => null, 'whatsapp_last_inbound_at' => null]);
        $task = $this->task(['title' => 'Hubungi Derol', 'contact_id' => $derol->id]);
        $approval = app(FollowUpDrafter::class)->draftForTask($task, Actor::user($this->ceo));

        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1]);

        $approval->refresh();
        $this->assertSame(ApprovalStatus::Failed, $approval->status);
        $this->assertStringContainsString('opt-in', $approval->error);
        $this->actingAs($this->ceo)->get('/decision-room?tab=failed')->assertSee($approval->code);
        $this->assertStringContainsString('Tindakan gagal', $this->lastWhatsAppTo('60123456789'));

        // Fix the cause, then retry.
        $derol->forceFill(['whatsapp_opt_in_at' => now(), 'whatsapp_last_inbound_at' => now()])->save();
        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/retry")->assertSessionHasNoErrors();

        $this->assertSame(ApprovalStatus::Executed, $approval->fresh()->status);
        $this->assertCount(1, $this->customerMessages());
    }

    public function test_outside_24h_window_without_template_fails_clearly(): void
    {
        $derol = $this->contact(['name' => 'Derol', 'phone' => '60111111111', 'whatsapp_opt_in_at' => now(), 'whatsapp_last_inbound_at' => now()->subDays(3)]);
        $task = $this->task(['title' => 'Hubungi Derol', 'contact_id' => $derol->id]);
        $approval = app(FollowUpDrafter::class)->draftForTask($task, Actor::user($this->ceo));

        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1]);

        $this->assertSame(ApprovalStatus::Failed, $approval->fresh()->status);
        $this->assertStringContainsString('24 hours', $approval->fresh()->error);

        config(['vice_ceo.whatsapp.templates.customer_followup' => 'customer_followup_v1']);
        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/retry");

        $this->assertSame(ApprovalStatus::Executed, $approval->fresh()->status);
        $sent = $this->customerMessages()[0];
        $this->assertSame('template', $sent['type']);
        $this->assertSame('customer_followup_v1', $sent['template']);
        $this->assertSame(1, CommunicationLog::where('approval_id', $approval->id)->where('type', 'customer_message')->count(), 'Retry reuses the same log/idempotency key.');
    }

    public function test_email_follow_up_for_contacts_without_phone(): void
    {
        Mail::fake();
        $contact = $this->contact(['name' => 'Siti', 'phone' => null, 'email' => 'siti@example.com']);
        $task = $this->task(['title' => 'Quotation Siti', 'contact_id' => $contact->id]);

        $approval = app(FollowUpDrafter::class)->draftForTask($task, Actor::user($this->ceo));
        $this->assertSame(ActionType::SendCustomerEmail, $approval->action_type);
        Mail::assertNothingSent();

        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1]);

        $this->assertSame(ApprovalStatus::Executed, $approval->fresh()->status);
        Mail::assertSent(HtmlMessage::class, fn ($m) => $m->hasTo('siti@example.com'));
    }

    public function test_level_three_decisions_are_recorded_but_never_executed(): void
    {
        $this->actingAs($this->ceo)->post('/decision-room', [
            'action_type' => 'commit_spending',
            'title' => 'Buy new oven',
            'details' => 'RM15,000 oven for outlet 2',
            'amount' => 15000,
        ])->assertRedirect();

        $approval = Approval::firstOrFail();
        $this->assertSame(3, $approval->authority_level->value);

        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1]);

        $approval->refresh();
        $this->assertSame(ApprovalStatus::Acknowledged, $approval->status);
        $this->assertNull($approval->executed_at);
    }

    public function test_reassignment_goes_through_decision_room(): void
    {
        $member = User::factory()->staff()->create(['business_id' => $this->business->id, 'name' => 'Aiman']);
        $task = $this->task(['title' => 'Prepare quotation']);

        $this->actingAs($this->ceo)->post("/tasks/{$task->id}/reassign", ['assigned_to' => $member->id])->assertRedirect();
        $this->assertNull($task->fresh()->assigned_to);

        $approval = Approval::firstOrFail();
        $this->actingAs($this->ceo)->post("/decision-room/{$approval->ulid}/approve", ['version' => 1]);

        $this->assertSame($member->id, $task->fresh()->assigned_to);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'action' => 'reassigned']);
    }

    public function test_expire_command(): void
    {
        $approval = $this->derolApproval();
        $approval->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('vice-ceo:expire-approvals')->assertSuccessful();

        $this->assertSame(ApprovalStatus::Expired, $approval->fresh()->status);
    }
}
