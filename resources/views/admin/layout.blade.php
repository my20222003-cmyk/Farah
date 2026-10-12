@php
    $role = auth()->user()->getRoleNames()->first() ?? 'system.admin';
    $navItems = [
        ['label' => 'Dashboard', 'route' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')],
        ['label' => 'Provider approvals', 'route' => route('admin.providers.pending'), 'active' => request()->routeIs('admin.providers*')],
    ];

    if (in_array($role, ['ops.moderator', 'system.admin'], true)) {
        $navItems[] = ['label' => 'All providers', 'route' => route('admin.providers'), 'active' => request()->routeIs('admin.providers')];
    }

    if ($role === 'system.admin') {
        $navItems[] = ['label' => 'Audit log', 'route' => route('admin.audit-log'), 'active' => request()->routeIs('admin.audit-log')];
    }

    $navItems[] = ['label' => 'Logout', 'route' => route('admin.logout'), 'active' => false, 'logout' => true];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Farah Admin')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; }
        .sidebar { min-height: 100vh; background: #0f172a; }
        .nav-link { color: #dfe7f5; }
        .nav-link:hover, .nav-link.active { background: #1e293b; color: #fff; }
        .card { border: 0; box-shadow: 0 8px 16px rgba(15,23,42,0.06); }
        .topbar { background: #fff; border-bottom: 1px solid #e2e8f0; }
        .stat-card { border-left: 4px solid #2563eb; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <aside class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-4 text-white border-bottom border-secondary">
                    <h4 class="mb-0">Farah Admin</h4>
                </div>
                <nav class="nav flex-column p-3">
                    @foreach($navItems as $item)
                        @if(!empty($item['logout']))
                            <a class="nav-link" href="{{ $item['route'] }}" onclick="event.preventDefault();document.getElementById('logout-form').submit();">{{ $item['label'] }}</a>
                        @else
                            <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['route'] }}">{{ $item['label'] }}</a>
                        @endif
                    @endforeach
                    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </nav>
            </aside>
            <main class="col-md-9 col-lg-10 p-0">
                <div class="topbar px-4 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ auth()->user()->name ?? 'Administrator' }}</strong>
                        <div class="small text-muted">{{ $role }}</div>
                    </div>
                    <span class="badge bg-primary rounded-pill">30-minute session</span>
                </div>
                <div class="p-4">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
