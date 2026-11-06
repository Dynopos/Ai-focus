<?php

namespace App\Http\Controllers;

use App\Jobs\SendCommunicationJob;
use App\Models\CommunicationLog;
use App\Services\Executive\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Communication Centre: every email and WhatsApp, in and out. */
class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->business->communicationLogs()->latest();

        foreach (['channel', 'status', 'direction'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        return view('communications.index', ['logs' => $query->paginate(30)->withQueryString()]);
    }

    public function show(CommunicationLog $log): View
    {
        Gate::authorize('view', $log);

        return view('communications.show', ['log' => $log]);
    }

    public function retry(Request $request, CommunicationLog $log, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $log);

        if (! $log->isRetryable()) {
            return back()->withErrors(['communication' => 'Only failed outbound messages can be retried.']);
        }

        // Customer messages tied to an approval must be retried from the Decision Room (re-validates the approval).
        if ($log->approval_id && $log->type === 'customer_message') {
            return redirect()->route('approvals.show', $log->approval)->withErrors(['communication' => 'Retry customer messages from the Decision Room.']);
        }

        SendCommunicationJob::dispatch($log->id);
        $audit->record($log->business_id, 'communication.retried', $log, [], Actor::user($request->user(), 'dashboard', $request->ip()));

        return back()->with('status', 'Message re-queued.');
    }
}
