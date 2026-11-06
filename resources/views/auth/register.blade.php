@extends('layouts.guest')
@section('title', 'Create account')
@section('content')
<form method="POST" action="{{ url('/register') }}">
    @csrf
    <div class="field"><label for="business_name">Business name</label><input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required></div>
    <div class="field"><label for="name">Your name (CEO)</label><input id="name" type="text" name="name" value="{{ old('name') }}" required></div>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="new-password"><div class="hint">At least 10 characters.</div></div>
    <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
    <button class="btn primary" type="submit" style="width:100%;justify-content:center;">Create account</button>
</form>
<p class="small muted" style="text-align:center;margin-bottom:0;">Already registered? <a href="{{ route('login') }}">Log in</a></p>
@endsection
