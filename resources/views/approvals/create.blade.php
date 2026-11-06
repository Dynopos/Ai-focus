@extends('layouts.app')
@section('title', 'Log a CEO decision')
@section('content')
<div class="topbar"><div><h1>Log a Level 3 decision</h1><p class="muted">Spending, contracts, hiring, financial or strategic commitments. The AI prepares a decision brief; only you act.</p></div></div>
<form method="POST" action="{{ route('approvals.store') }}" class="card" style="max-width:760px;">
    @csrf
    <div class="field"><label for="action_type">Type</label>
        <select id="action_type" name="action_type">@foreach ($types as $type)<option value="{{ $type->value }}" @selected(old('action_type') === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
    <div class="field"><label for="title">Title</label><input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="200"></div>
    <div class="field"><label for="details">Details</label><textarea id="details" name="details" required>{{ old('details') }}</textarea></div>
    <div class="field"><label for="amount">Amount (RM, optional)</label><input id="amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}"></div>
    <button class="btn primary" type="submit">Prepare decision brief</button>
</form>
@endsection
