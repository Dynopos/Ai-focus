<?php

namespace App\Services\Executive;

use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Business;
use App\Models\CommunicationLog;
use App\Services\Messaging\CommunicationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

class AutomationService
{
    /** How long after the scheduled time a missed run may still go out (e.g. after a restart). */
    private const CATCH_UP_MINUTES = 60;

    public function __construct(
        private readonly ReportBuilder $reports,
        private readonly CommunicationService $communications,
    ) {}

    public function ensureDefaults(Business $business): void
    {
        foreach (Automation::DEFAULTS as $key => $default) {
            $business->automations()->firstOrCreate(['key' => $key], [
                'enabled' => false,
                'send_time' => $default['send_time'],
                'channel' => $default['channel'],
                'respect_quiet_hours' => true,
            ]);
        }
    }

    /** Called every minute by the scheduler. Returns how many runs were dispatched. */
    public function dispatchDue(?Carbon $now = null): int
    {
        if (! config('vice_ceo.automations_enabled')) {
            return 0;
        }

        $dispatched = 0;

        Automation::with('business')->where('enabled', true)->get()->each(function (Automation $automation) use ($now, &$dispatched) {
            $business = $automation->business;
            $local = ($now ?? now())->copy()->timezone($business->timezone ?: config('vice_ceo.timezone'));
            $scheduled = $local->copy()->setTimeFromTimeString($automation->send_time);

            if ($local->lt($scheduled) || $local->gt($scheduled->copy()->addMinutes(self::CATCH_UP_MINUTES))) {
                return;
            }

            try {
                // Unique (business, automation, local date) => never twice a day, even with several schedulers.
                $run = AutomationRun::create([
                    'business_id' => $business->id,
                    'automation_key' => $automation->key,
                    'run_date' => $local->toDateString(),
                    'status' => 'dispatched',
                ]);
            } catch (UniqueConstraintViolationException) {
                return;
            }

            if ($automation->respect_quiet_hours && $business->isQuietTime($local)) {
                $run->update(['status' => 'skipped', 'note' => 'Quiet hours']);

                return;
            }

            RunAutomationJob::dispatch($automation->id, $run->id);
            $dispatched++;
        });

        return $dispatched;
    }

    /** Build and queue the message for one automation. $runKey makes the send idempotent. */
    public function run(Automation $automation, string $runKey): ?CommunicationLog
    {
        $business = $automation->business;
        $ceo = $business->primaryCeo();

        $automation->forceFill(['last_run_at' => now()])->save();

        if ($automation->channel === 'email') {
            $to = $business->reportEmail();
            if (! $to) {
                return null;
            }
            $moment = $automation->key === Automation::EVENING_EMAIL ? 'evening' : 'morning';
            $email = $this->reports->email($business, $moment);

            return $this->communications->queue($business, 'email', [
                'type' => $moment === 'evening' ? 'evening_report' : 'morning_briefing',
                'recipient' => $to,
                'recipient_name' => $ceo?->name,
                'subject' => $email['subject'],
                'body' => $email['html'],
                'idempotency_key' => "auto:{$business->id}:{$automation->key}:{$runKey}",
            ]);
        }

        if (! $ceo?->canCommandViaWhatsApp()) {
            return null;
        }

        $body = $automation->key === Automation::WA_PROGRESS
            ? $this->reports->whatsappProgress($business)
            : $this->reports->whatsappPriorities($business);

        return $this->communications->queue($business, 'whatsapp', [
            'type' => $automation->key,
            'recipient' => $ceo->phone,
            'recipient_name' => $ceo->name,
            'body' => $body,
            'template' => 'ceo_update',
            'idempotency_key' => "auto:{$business->id}:{$automation->key}:{$runKey}",
        ]);
    }
}
