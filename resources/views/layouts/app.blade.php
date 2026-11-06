@php
    $user = auth()->user();
    $pendingCount = $user?->business?->approvals()->where('status', 'pending')->where('expires_at', '>', now())->count() ?? 0;
    $failedCount = $user?->business?->communicationLogs()->where('status', 'failed')->count() ?? 0;
    $nav = [
        ['dashboard', 'Executive Control Panel', null],
        ['tasks.index', 'Tasks', null],
        ['approvals.index', 'Decision Room', $pendingCount],
        ['communications.index', 'Communication Centre', $failedCount],
        ['contacts.index', 'Customers', null],
        ['memory.index', 'Executive Memory', null],
        ['automations.index', 'Automations', null],
        ['settings.edit', 'Settings', null],
        ['audit.index', 'Audit Log', null],
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Dyno Focus</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <a href="{{ route('dashboard') }}" class="row" style="align-items:center;color:inherit;text-decoration:none;">
                <img src="{{ asset('images/dyno-focus-logo.webp') }}" alt="Dyno Focus logo">
                <div><b>DYNO FOCUS</b><span>AI VICE CEO</span></div>
            </a>
            <button class="menu-toggle" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Toggle menu">Menu</button>
        </div>
        <div class="nav-section">{{ $user->business->name }}</div>
        <nav class="nav">
            @foreach ($nav as [$route, $label, $count])
                <a href="{{ route($route) }}" class="{{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'active' : '' }}">
                    <span>{{ $label }}</span>
                    @if ($count)<span class="count">{{ $count }}</span>@endif
                </a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Log out ({{ $user->name }})</button></form>
        <div class="tagline">Think Like a CEO. Act Like a Vice CEO.<br>You Lead. We Execute.</div>
    </aside>
    <main class="main">
        @if (session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="flash err"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
