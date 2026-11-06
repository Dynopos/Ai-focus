<?php

namespace App\Services\Executive;

use App\Models\AuditLog;
use App\Support\Actor;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function record(?int $businessId, string $event, ?Model $subject = null, array $data = [], ?Actor $actor = null): AuditLog
    {
        $actor ??= Actor::system();

        return AuditLog::create([
            'business_id' => $businessId,
            'user_id' => $actor->user?->id,
            'actor' => $actor->type,
            'channel' => $actor->channel,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
            'ip' => $actor->ip,
        ]);
    }
}
