@extends('layouts.app')
@section('title', 'Edit memory')
@section('content')
<div class="topbar"><h1>Correct memory</h1></div>
<form method="POST" action="{{ route('memory.update', $memory) }}" class="card" style="max-width:720px;">
    @csrf @method('PUT')
    <div class="field"><label for="category">Category</label><select id="category" name="category">@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($memory->category === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="title">Title</label><input id="title" type="text" name="title" value="{{ $memory->title }}" required></div>
    <div class="field"><label for="content">Content</label><textarea id="content" name="content" required>{{ $memory->content }}</textarea></div>
    <div class="field"><label class="check"><input type="checkbox" name="is_verified" value="1" @checked($memory->is_verified)> Verified fact</label></div>
    <div class="actions"><button class="btn primary" type="submit">Save</button><a class="btn" href="{{ route('memory.index') }}">Cancel</a></div>
</form>
@endsection
