<?php

namespace App\Support;

use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;

final class Ui
{
    public static function taskClass(TaskStatus $status): string
    {
        return match ($status) {
            TaskStatus::Completed => 'ok',
            TaskStatus::WaitingForCeo => 'red',
            TaskStatus::WaitingForCustomer => 'warn',
            TaskStatus::InProgress => 'info',
            default => '',
        };
    }

    public static function approvalClass(ApprovalStatus $status): string
    {
        return match ($status) {
            ApprovalStatus::Pending => 'warn',
            ApprovalStatus::Approved, ApprovalStatus::Executing, ApprovalStatus::Acknowledged => 'info',
            ApprovalStatus::Executed => 'ok',
            ApprovalStatus::Failed => 'red',
            default => '',
        };
    }

    public static function commClass(string $status): string
    {
        return match ($status) {
            'sent', 'delivered', 'read', 'received' => 'ok',
            'failed' => 'red',
            'queued', 'sending' => 'info',
            'skipped' => 'warn',
            default => '',
        };
    }

    public static function priorityClass(string $priority): string
    {
        return match ($priority) {
            'critical' => 'red',
            'high' => 'warn',
            default => '',
        };
    }
}
