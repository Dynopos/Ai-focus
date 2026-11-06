@extends('layouts.guest')
@section('title', 'Log in')
@section('content')
<form method="POST" action="{{ url('/login') }}">
    @csrf
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Remember me</label></div>
    <button class="btn primary" type="submit" style="width:100%;justify-content:center;">Log in</button>
</form>
@if (config('vice_ceo.registration_enabled'))
    <p class="small muted" style="text-align:center;margin-bottom:0;">New business? <a href="{{ route('register') }}">Create your account</a></p>
@endif
@endsection
