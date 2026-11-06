@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="topbar"><h1>Settings</h1></div>
<div class="grid grid-2">
    <form method="POST" action="{{ route('settings.update') }}" class="card">
        @csrf @method('PUT')
        <h2>Business</h2>
        <div class="field"><label for="name">Business name</label><input id="name" type="text" name="name" value="{{ old('name', $business->name) }}" required></div>
        <div class="field"><label for="profile">Company profile</label><textarea id="profile" name="profile">{{ old('profile', $business->profile) }}</textarea><div class="hint">What you sell, to whom, and how you make money.</div></div>
        <div class="field"><label for="goals">Business goals &amp; KPIs</label><textarea id="goals" name="goals">{{ old('goals', $business->goals) }}</textarea></div>
        <div class="field"><label for="report_email">Report email</label><input id="report_email" type="email" name="report_email" value="{{ old('report_email', $business->report_email) }}" placeholder="{{ $user->email }}"><div class="hint">Morning and evening reports go here (defaults to the CEO's login email).</div></div>
        <div class="field"><label for="whatsapp_phone_number_id">WhatsApp Phone Number ID (Meta)</label><input id="whatsapp_phone_number_id" type="text" name="whatsapp_phone_number_id" value="{{ old('whatsapp_phone_number_id', $business->whatsapp_phone_number_id) }}"><div class="hint">Routes inbound messages on that number to this business. Leave empty to use the server default.</div></div>
        @can('manage-business')<button class="btn primary" type="submit">Save</button>@else<p class="muted small">Only the CEO can change business settings.</p>@endcan
    </form>

    <section class="card">
        <h2>Your WhatsApp (CEO commands)</h2>
        @if ($user->phone_verified_at)
            <p><span class="badge ok">Verified</span> {{ $user->phone }} since {{ $user->phone_verified_at->format('d M Y') }}</p>
            <p class="small muted">Only this number can give commands or approvals to your AI Vice CEO.</p>
        @elseif ($user->phone)
            <p><span class="badge warn">Not verified</span> {{ $user->phone }}</p>
        @endif

        @if ($verificationCode)
            <div class="flash ok">
                From <b>{{ $user->phone }}</b>, send this WhatsApp message to your business number within 30 minutes:<br>
                <span style="font-size:1.4rem;font-weight:700;letter-spacing:2px;">VERIFY {{ $verificationCode }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.phone') }}">
            @csrf
            <div class="field"><label for="phone">Your WhatsApp number</label><input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="012-345 6789" required></div>
            <button class="btn" type="submit">{{ $user->phone_verified_at ? 'Change number' : 'Get verification code' }}</button>
            <p class="hint">Sending the code from your phone proves you own the number and opts you in to WhatsApp messages from Dyno Focus.</p>
        </form>
    </section>
</div>
@endsection
