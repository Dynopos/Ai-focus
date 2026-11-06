<?php

namespace App\Jobs;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Services\Executive\AutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunAutomationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $automationId, public int $runId) {}

    public function handle(AutomationService $automations): void
    {
        $automation = Automation::with('business')->find($this->automationId);
        $run = AutomationRun::find($this->runId);

        if (! $automation || ! $run || $run->status === 'completed') {
            return;
        }

        try {
            $log = $automations->run($automation, $run->run_date->toDateString());
            $run->update([
                'status' => $log ? 'completed' : 'skipped',
                'note' => $log ? "communication_log #{$log->id}" : 'No recipient configured (email / verified WhatsApp).',
            ]);
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'note' => mb_substr($e->getMessage(), 0, 500)]);

            throw $e;
        }
    }
}
