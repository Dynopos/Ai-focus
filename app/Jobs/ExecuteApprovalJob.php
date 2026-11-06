<?php

namespace App\Jobs;

use App\Services\Executive\ActionExecutor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteApprovalJob implements ShouldQueue
{
    use Queueable;

    /** Executor marks failures itself; never auto-retry an external action. */
    public int $tries = 1;

    public function __construct(public int $approvalId) {}

    public function handle(ActionExecutor $executor): void
    {
        $executor->execute($this->approvalId);
    }
}
