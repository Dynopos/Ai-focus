<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Services\Messaging\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCommunicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $communicationLogId) {}

    public function handle(CommunicationService $communications): void
    {
        $log = CommunicationLog::find($this->communicationLogId);

        if (! $log || ! in_array($log->status, [CommunicationLog::STATUS_QUEUED, CommunicationLog::STATUS_FAILED], true)) {
            return;
        }

        // Respect quiet hours for automated, non-urgent messages: try again later.
        if (in_array($log->type, ['wa_priorities', 'wa_progress', 'morning_briefing', 'evening_report'], true) && $log->business->isQuietTime()) {
            $log->update(['status' => CommunicationLog::STATUS_SKIPPED, 'error' => 'Skipped: quiet hours.']);

            return;
        }

        $result = $communications->deliver($log);

        if (! $result->ok && $result->retryable && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }
}
