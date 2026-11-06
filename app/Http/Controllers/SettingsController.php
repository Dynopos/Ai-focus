<?php

namespace App\Http\Controllers;

use App\Services\Executive\AuditLogger;
use App\Support\Actor;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'business' => $request->user()->business,
            'user' => $request->user(),
            'verificationCode' => $request->session()->get('verification_code'),
            'businessNumber' => config('vice_ceo.whatsapp.phone_number_id'),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-business');
        $business = $request->user()->business;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'profile' => ['nullable', 'string', 'max:3000'],
            'goals' => ['nullable', 'string', 'max:3000'],
            'report_email' => ['nullable', 'email', 'max:190'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:40', Rule::unique('businesses', 'whatsapp_phone_number_id')->ignore($business->id)],
        ]);

        $business->update($data);
        $audit->record($business->id, 'business.updated', $business, ['fields' => array_keys($data)], Actor::user($request->user(), 'dashboard', $request->ip()));

        return back()->with('status', 'Business settings saved.');
    }

    /**
     * Start WhatsApp verification: the CEO proves they own the number by
     * sending "VERIFY <code>" from it to the business WhatsApp number.
     */
    public function startPhoneVerification(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = Phone::normalize($data['phone'], (string) config('vice_ceo.whatsapp.default_country_code', '60'));

        if (! $phone) {
            return back()->withErrors(['phone' => 'Enter a valid phone number, e.g. 012-345 6789 or +60123456789.']);
        }

        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'phone' => $phone,
            'phone_verified_at' => null, // changing number always requires re-verification
            'phone_verification_hash' => hash('sha256', $user->id.'|'.$code),
            'phone_verification_expires_at' => now()->addMinutes(30),
        ])->save();

        $audit->record($user->business_id, 'user.phone_verification_started', $user, ['phone' => Phone::mask($phone)], Actor::user($user, 'dashboard', $request->ip()));

        return back()->with('verification_code', $code)->with('status', 'Now send the code from your WhatsApp.');
    }
}
