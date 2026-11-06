@extends('layouts.app')
@section('title', 'Executive Memory')
@section('content')
<div class="topbar"><div><h1>Executive Memory</h1><p class="muted">What your AI Vice CEO knows about the business. Review, correct or delete anything. Facts are separated from assumptions.</p></div></div>
<div class="grid grid-2">
    <section class="card">
        <h2>Add context</h2>
        <form method="POST" action="{{ route('memory.store') }}">
            @csrf
            <div class="field"><label for="category">Category</label><select id="category" name="category">@foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="title">Title</label><input id="title" type="text" name="title" required maxlength="160"></div>
            <div class="field"><label for="content">Content</label><textarea id="content" name="content" required maxlength="3000"></textarea></div>
            <div class="field"><label class="check"><input type="checkbox" name="is_verified" value="1"> This is a verified fact (not an assumption)</label></div>
            <button class="btn primary" type="submit">Save</button>
            <p class="hint">Don't store private information the AI doesn't need.</p>
        </form>
    </section>
    <div>
        @forelse ($categories as $key => $label)
            @if ($memories->has($key))
                <section class="card" style="margin-bottom:12px;">
                    <h2>{{ $label }}</h2>
                    <ul class="list">
                        @foreach ($memories[$key] as $m)
                            <li>
                                <div class="row"><div class="grow"><b>{{ $m->title }}</b> <span class="badge {{ $m->is_verified ? 'ok' : 'warn' }}">{{ $m->is_verified ? 'Fact' : 'Assumption' }}</span></div>
                                    <a class="btn small" href="{{ route('memory.edit', $m) }}">Edit</a>
                                    <form method="POST" action="{{ route('memory.destroy', $m) }}" onsubmit="return confirm('Delete permanently?')">@csrf @method('DELETE')<button class="btn small" type="submit">Delete</button></form></div>
                                <div class="small">{!! nl2br(e($m->content)) !!}</div>
                                <div class="small muted">{{ $m->source }} · {{ $m->updated_at->diffForHumans() }}</div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @empty
        @endforelse
        @if ($memories->isEmpty())<section class="card muted">No saved context yet. Start with your company profile and this quarter's goals.</section>@endif
    </div>
</div>
@endsection
