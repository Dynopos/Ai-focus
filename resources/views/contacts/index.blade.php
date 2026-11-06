@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<div class="topbar"><div><h1>Customers</h1><p class="muted">People you follow up with. WhatsApp messages need recorded opt-in.</p></div><a class="btn primary" href="{{ route('contacts.create') }}">+ New customer</a></div>
<form method="GET" class="actions" style="margin-bottom:12px;"><input type="search" name="q" value="{{ $search }}" placeholder="Search customers" style="max-width:320px;"><button class="btn" type="submit">Search</button></form>
<div class="card table-wrap">
    <table class="table">
        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>WhatsApp opt-in</th><th>Open tasks</th><th></th></tr></thead>
        <tbody>
        @forelse ($contacts as $contact)
            <tr>
                <td><b>{{ $contact->name }}</b>@if ($contact->company)<div class="small muted">{{ $contact->company }}</div>@endif</td>
                <td class="small">{{ $contact->phone ?? '—' }}</td>
                <td class="small">{{ $contact->email ?? '—' }}</td>
                <td>{!! $contact->whatsapp_opt_in_at ? '<span class="badge ok">Yes</span>' : '<span class="badge">No</span>' !!}</td>
                <td>{{ $contact->tasks_count }}</td>
                <td class="actions"><a class="btn small" href="{{ route('contacts.edit', $contact) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No customers yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $contacts->links('pagination') }}
</div>
@endsection
