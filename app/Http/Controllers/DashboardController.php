<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Models\CommunicationLog;
use App\Services\Ai\AiClient;
use App\Services\Executive\CeoBrain;
use App\Services\Executive\PriorityEngine;
use App\Services\Messaging\CommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/** Executive Control Panel. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, PriorityEngine $priorities, CeoBrain $brain, CommunicationService $communications): View
    {
        $business = $request->user()->business;
        $snapshot = $brain->snapshot($business);

        $approvals = $business->approvals()->latest()->limit(40)->get()->groupBy(fn ($a) => match ($a->status) {
            ApprovalStatus::Pending => 'proposed',
            ApprovalStatus::Approved, ApprovalStatus::Executing, ApprovalStatus::Acknowledged => 'approved',
            ApprovalStatus::Executed => 'executed',
            ApprovalStatus::Failed => 'failed',
            default => 'closed',
        });

        $failedComms = $business->communicationLogs()->where('status', CommunicationLog::STATUS_FAILED)->count();

        return view('dashboard', [
            'business' => $business,
            'top' => $priorities->rank($business, 3),
            'snapshot' => $snapshot,
            'approvals' => $approvals,
            'recommendations' => $brain->recommendations($business),
            'communications' => $business->communicationLogs()->latest()->limit(8)->get(),
            'health' => $this->health($business, $communications, $failedComms),
        ]);
    }

    private function health($business, CommunicationService $communications, int $failedComms): array
    {
        $ceo = $business->primaryCeo();
        $lastScheduler = Cache::get('vice_ceo:scheduler_heartbeat');

        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $pendingJobs = $failedJobs = null;
        }

        return [
            'whatsapp' => [
                'ok' => $communications->whatsappConfigured() && $communications->whatsappDriver() === 'meta',
                'label' => $communications->whatsappDriver() === 'meta'
                    ? ($communications->whatsappConfigured() ? 'Meta Cloud API connected' : 'Meta driver selected, credentials missing')
                    : 'Mock transport (development) — messages are logged, not sent',
            ],
            'ceo_phone' => [
                'ok' => (bool) $ceo?->canCommandViaWhatsApp(),
                'label' => $ceo?->canCommandViaWhatsApp() ? 'CEO number verified' : 'CEO WhatsApp number not verified',
            ],
            'email' => [
                'ok' => ! in_array(config('mail.default'), ['log', 'array'], true),
                'label' => 'Mailer: '.config('mail.default').(in_array(config('mail.default'), ['log', 'array'], true) ? ' (development — not delivered)' : ''),
            ],
            'ai' => [
                'ok' => app(AiClient::class)->available(),
                'label' => app(AiClient::class)->available() ? 'Claude connected ('.config('vice_ceo.ai.model').')' : 'AI not configured — using rule-based reasoning',
            ],
            'scheduler' => [
                'ok' => config('vice_ceo.automations_enabled') && $lastScheduler && now()->diffInMinutes($lastScheduler) < 5,
                'label' => ! config('vice_ceo.automations_enabled')
                    ? 'Automations master switch OFF'
                    : ($lastScheduler ? 'Last tick '.Carbon::parse($lastScheduler)->diffForHumans() : 'No scheduler tick yet'),
            ],
            'queue' => [
                'ok' => $failedJobs === 0,
                'label' => $pendingJobs === null ? 'Queue tables missing' : "{$pendingJobs} queued · {$failedJobs} failed jobs",
            ],
            'failed_communications' => $failedComms,
        ];
    }
}
