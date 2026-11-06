<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Services\Executive\AuditLogger;
use App\Services\Executive\AutomationService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(Request $request, AutomationService $automations): View
    {
        $business = $request->user()->business;
        $automations->ensureDefaults($business);

        return view('automations.index', [
            'business' => $business,
            'automations' => $business->automations()->orderBy('send_time')->get(),
            'runs' => AutomationRun::where('business_id', $business->id)->latest()->limit(30)->get(),
            'masterSwitch' => config('vice_ceo.automations_enabled'),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-business');
        $business = $request->user()->business;

        $data = $request->validate([
            'automations' => ['array'],
            'automations.*.enabled' => ['nullable', 'boolean'],
            'automations.*.send_time' => ['required', 'date_format:H:i'],
            'automations.*.channel' => ['required', 'in:email,whatsapp'],
            'automations.*.respect_quiet_hours' => ['nullable', 'boolean'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        foreach ($data['automations'] ?? [] as $id => $settings) {
            $business->automations()->whereKey($id)->update([
                'enabled' => (bool) ($settings['enabled'] ?? false),
                'send_time' => $settings['send_time'],
                'channel' => $settings['channel'],
                'respect_quiet_hours' => (bool) ($settings['respect_quiet_hours'] ?? false),
            ]);
        }

        $business->update([
            'quiet_hours_start' => $data['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $data['quiet_hours_end'] ?? null,
            'timezone' => $data['timezone'],
        ]);

        $audit->record($business->id, 'automations.updated', $business, ['automations' => $data['automations'] ?? []], Actor::user($request->user(), 'dashboard', $request->ip()));

        return back()->with('status', 'Automation settings saved.');
    }

    /** CEO-triggered "send now" (e.g. to preview a briefing). */
    public function runNow(Request $request, Automation $automation, AutomationService $automations, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-business');
        abort_unless($automation->business_id === $request->user()->business_id, 404);

        $log = $automations->run($automation, 'manual-'.now()->format('YmdHis'));
        $audit->record($automation->business_id, 'automation.run_now', $automation, ['log_id' => $log?->id], Actor::user($request->user(), 'dashboard', $request->ip()));

        return $log
            ? redirect()->route('communications.show', $log)->with('status', 'Queued. Check the delivery status below.')
            : back()->withErrors(['automation' => 'No recipient: set a report email, or verify the CEO WhatsApp number in Settings.']);
    }
}
