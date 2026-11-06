@extends('layouts.app')
@section('title', $contact->exists ? 'Edit customer' : 'New customer')
@section('content')
<div class="topbar"><h1>{{ $contact->exists ? 'Edit '.$contact->name : 'New customer' }}</h1></div>
<form method="POST" action="{{ $contact->exists ? route('contacts.update', $contact) : route('contacts.store') }}" class="card" style="max-width:720px;">
    @csrf
    @if ($contact->exists) @method('PUT') @endif
    <div class="grid grid-2">
        <div class="field"><label for="name">Name</label><input id="name" type="text" name="name" value="{{ old('name', $contact->name) }}" required></div>
        <div class="field"><label for="company">Company</label><input id="company" type="text" name="company" value="{{ old('company', $contact->company) }}"></div>
        <div class="field"><label for="phone">WhatsApp number</label><input id="phone" type="text" name="phone" value="{{ old('phone', $contact->phone) }}" placeholder="012-345 6789"></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $contact->email) }}"></div>
    </div>
    <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $contact->notes) }}</textarea><div class="hint">Avoid storing sensitive personal data you don't need (PDPA).</div></div>
    <div class="field"><label class="check"><input type="hidden" name="whatsapp_opt_in" value="0"><input type="checkbox" name="whatsapp_opt_in" value="1" @checked(old('whatsapp_opt_in', (bool) $contact->whatsapp_opt_in_at))> Customer agreed to receive WhatsApp messages from us</label>
        @if ($contact->whatsapp_opt_in_at)<div class="hint">Recorded {{ $contact->whatsapp_opt_in_at->format('d M Y H:i') }}</div>@endif</div>
    <div class="actions"><button class="btn primary" type="submit">Save</button><a class="btn" href="{{ route('contacts.index') }}">Cancel</a></div>
</form>
@if ($contact->exists)
    <form method="POST" action="{{ route('contacts.destroy', $contact) }}" style="margin-top:12px;" onsubmit="return confirm('Delete this customer?')">@csrf @method('DELETE')<button class="btn small" type="submit">Delete customer</button></form>
@endif
@endsection
