<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Dyno Focus</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth">
    <div class="card">
        <img class="auth-logo" src="{{ asset('images/dyno-focus-logo.webp') }}" alt="Dyno Focus · AI Vice CEO">
        <p class="muted" style="text-align:center;margin-top:0;">Thinks Like a CEO. Acts Like a Vice CEO.</p>
        @if ($errors->any())
            <div class="flash err"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </div>
</div>
</body>
</html>
