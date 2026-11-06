<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'timezone', 'profile', 'goals', 'whatsapp_phone_number_id', 'report_email', 'quiet_hours_start', 'quiet_hours_end', 'settings'])]
class Business extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function ceos(): HasMany
    {
        return $this->users()->where('role', User::ROLE_CEO);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function localNow(): Carbon
    {
        return now($this->timezone ?: config('vice_ceo.timezone'));
    }

    /** The CEO who receives reports and approval requests. */
    public function primaryCeo(): ?User
    {
        return $this->ceos()->orderBy('id')->first();
    }

    public function reportEmail(): ?string
    {
        return $this->report_email ?: $this->primaryCeo()?->email;
    }

    public function isQuietTime(?Carbon $at = null): bool
    {
        if (! $this->quiet_hours_start || ! $this->quiet_hours_end) {
            return false;
        }

        $now = ($at ?? $this->localNow())->format('H:i');
        $start = substr($this->quiet_hours_start, 0, 5);
        $end = substr($this->quiet_hours_end, 0, 5);

        return $start <= $end
            ? ($now >= $start && $now < $end)
            : ($now >= $start || $now < $end); // window crosses midnight
    }
}
