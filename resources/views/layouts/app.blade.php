<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - DEGMS</title>
    @vite(['resources/css/bootstrap.css', 'resources/js/bootstrap.js'])
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-degms mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">DEGMS &middot; Driving Range</a>

        <div class="navbar-nav me-auto">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Bay Monitor</a>
            <a class="nav-link {{ request()->routeIs('dtr.*') ? 'active' : '' }}" href="{{ route('dtr.show') }}">Employee DTR</a>
            @if (auth()->user()->isAdmin())
                <a class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.index') }}">Admin</a>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            <span class="navbar-text small">
                Welcome, {{ auth()->user()->name }}
                <span class="badge text-bg-light ms-1">{{ auth()->user()->isAdmin() ? 'Admin' : 'Front Desk' }}</span>
            </span>
            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-light">Log-out</button>
            </form>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 pb-5">
    @php
        // Read the one-shot flashes into locals, then consume them so the
        // 1s status poller can never replay or race them.
        $flashSuccess = session('success');
        $flashReceiptUrl = session('receipt_url');
        $flashReceiptLabel = session('receipt_label');
        $flashErrors = $errors->all();
        session()->forget(['success', 'receipt_url', 'receipt_label', 'errors']);
    @endphp

    @if ($flashSuccess)
        <div class="alert alert-success alert-dismissible fade show d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span>{{ $flashSuccess }}</span>
            @if ($flashReceiptUrl)
                <a href="{{ $flashReceiptUrl }}" class="btn btn-sm btn-success no-print">
                    &#128424; View / print receipt {{ $flashReceiptLabel ?? '' }}
                </a>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (count($flashErrors))
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($flashErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
