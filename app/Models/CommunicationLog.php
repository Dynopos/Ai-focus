<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'channel', 'direction', 'type', 'recipient', 'recipient_name', 'subject', 'body', 'payload',
    'provider', 'provider_message_id', 'status', 'attempts', 'error', 'provider_response', 'idempotency_key',
    'approval_id', 'sent_at',
])]
class CommunicationLog extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_RECEIVED = 'received';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'provider_response' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function isRetryable(): bool
    {
        return $this->direction === 'outbound' && $this->status === self::STATUS_FAILED;
    }
}
