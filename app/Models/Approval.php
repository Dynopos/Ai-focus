<?php

namespace App\Models;

use App\Enums\ActionType;
use App\Enums\ApprovalStatus;
use App\Enums\AuthorityLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'task_id', 'contact_id', 'requested_by_user_id', 'requested_by', 'action_type', 'authority_level',
    'title', 'situation', 'analysis', 'recommendation', 'expected_impact', 'payload', 'payload_hash', 'version',
    'notified_version', 'recipient_name', 'recipient_address', 'preview', 'status', 'expires_at',
])]
class Approval extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'action_type' => ActionType::class,
            'authority_level' => AuthorityLevel::class,
            'status' => ApprovalStatus::class,
            'payload' => 'array',
            'execution_result' => 'array',
            'expires_at' => 'datetime',
            'decided_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /** ULID lives in its own column; the integer id stays the primary key. */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending && ! $this->isExpired();
    }

    public static function hashPayload(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
