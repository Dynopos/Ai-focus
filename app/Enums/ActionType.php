<?php

namespace App\Enums;

/**
 * Every action the AI Vice CEO can propose. The authority level is fixed in
 * code: the AI (or a prompt) can never lower it.
 */
enum ActionType: string
{
    // Level 2: executed by the system after explicit CEO approval.
    case SendCustomerWhatsApp = 'send_customer_whatsapp';
    case SendCustomerEmail = 'send_customer_email';
    case ReassignTask = 'reassign_task';
    case UpdateSensitiveRecord = 'update_sensitive_record';

    // Level 3: AI may analyse and recommend; only the CEO acts.
    case CommitSpending = 'commit_spending';
    case SignContract = 'sign_contract';
    case HireOrDismiss = 'hire_or_dismiss';
    case FinancialCommitment = 'financial_commitment';
    case StrategicCommitment = 'strategic_commitment';

    public function authorityLevel(): AuthorityLevel
    {
        return match ($this) {
            self::SendCustomerWhatsApp,
            self::SendCustomerEmail,
            self::ReassignTask,
            self::UpdateSensitiveRecord => AuthorityLevel::CeoApproval,
            default => AuthorityLevel::CeoOnly,
        };
    }

    public function isExecutable(): bool
    {
        return $this->authorityLevel() === AuthorityLevel::CeoApproval;
    }

    public function label(): string
    {
        return match ($this) {
            self::SendCustomerWhatsApp => 'Send WhatsApp to customer',
            self::SendCustomerEmail => 'Send email to customer',
            self::ReassignTask => 'Reassign task',
            self::UpdateSensitiveRecord => 'Update sensitive record',
            self::CommitSpending => 'Commit company spending',
            self::SignContract => 'Sign contract',
            self::HireOrDismiss => 'Hire or dismiss employee',
            self::FinancialCommitment => 'Financial commitment',
            self::StrategicCommitment => 'Strategic commitment',
        };
    }
}
