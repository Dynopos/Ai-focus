<?php

use App\Services\Executive\ApprovalService;
use App\Services\Executive\AutomationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('vice-ceo:dispatch-automations', function (AutomationService $automations) {
    Cache::put('vice_ceo:scheduler_heartbeat', now()->toIso8601String(), now()->addDay());

    if (! config('vice_ceo.automations_enabled')) {
        $this->comment('Automations are disabled (VICE_CEO_AUTOMATIONS_ENABLED=false).');

        return;
    }
    $this->info('Dispatched '.$automations->dispatchDue().' automation run(s).');
})->purpose('Queue any scheduled briefings/reminders that are due now');

Artisan::command('vice-ceo:expire-approvals', function (ApprovalService $approvals) {
    $this->info('Expired '.$approvals->expireStale().' approval request(s).');
})->purpose('Expire CEO approval requests past their deadline');

// Requires: * * * * * php artisan schedule:run   and a queue worker (php artisan queue:work)
Schedule::command('vice-ceo:dispatch-automations')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('vice-ceo:expire-approvals')->everyFiveMinutes()->withoutOverlapping();
