<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';       // proposed, waiting for CEO
    case Approved = 'approved';     // CEO approved, queued for execution
    case Executing = 'executing';   // claimed by the executor (single-use lock)
    case Executed = 'executed';     // done
    case Failed = 'failed';         // execution failed; can be retried from the dashboard
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Acknowledged = 'acknowledged'; // Level 3 decision recorded; nothing executed by AI

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Executed, self::Rejected, self::Expired, self::Cancelled, self::Acknowledged], true);
    }
}
