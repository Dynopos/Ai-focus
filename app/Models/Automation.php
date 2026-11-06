<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'enabled', 'send_time', 'channel', 'respect_quiet_hours', 'last_run_at'])]
class Automation extends Model
{
    public const MORNING_EMAIL = 'morning_email';

    public const WA_PRIORITIES = 'wa_priorities';

    public const WA_PROGRESS = 'wa_progress';

    public const EVENING_EMAIL = 'evening_email';

    /** Default schedule (Asia/Kuala_Lumpur). All start disabled until the CEO enables them. */
    public const DEFAULTS = [
        self::MORNING_EMAIL => ['send_time' => '08:30', 'channel' => 'email', 'label' => 'Morning executive briefing'],
        self::WA_PRIORITIES => ['send_time' => '09:00', 'channel' => 'whatsapp', 'label' => 'Top priorities'],
        self::WA_PROGRESS => ['send_time' => '14:00', 'channel' => 'whatsapp', 'label' => 'Progress check'],
        self::EVENING_EMAIL => ['send_time' => '18:00', 'channel' => 'email', 'label' => 'Evening executive report'],
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'respect_quiet_hours' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function label(): string
    {
        return self::DEFAULTS[$this->key]['label'] ?? $this->key;
    }
}
