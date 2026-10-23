<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('platform.company_short') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    @stack('styles')
</head>
<body class="portal">

<header class="masthead">
    <div class="masthead-row">
        <a class="brand" href="{{ route('dashboard') }}">
            @include('partials.mark')
            <span>{{ config('platform.company_short') }}</span>
        </a>
        <div class="masthead-user">
            <a href="#" id="admin-panel-link" class="chip chip-admin" style="display:none;">Admin panel ↗</a>
            @yield('topbar-actions')
            <a href="{{ route('user.profile.index') }}" class="who" title="Profile">
                <span class="who-dot" aria-hidden="true"></span>
                <span id="sidebar-username">Account</span>
            </a>
            <form id="sidebar-logout-form" method="POST" action="">
                @csrf
                <button type="submit" class="link-btn">Sign out</button>
            </form>
        </div>
    </div>
    <nav class="tabstrip" aria-label="Primary">
        <div class="tabgroup">
            <a href="{{ route('dashboard') }}" class="tab {{ request()->routeIs('user.dashboard') ? 'is-active' : '' }}">Overview</a>
            <a href="{{ route('user.transactions.index') }}" class="tab {{ request()->routeIs('user.transactions.*') ? 'is-active' : '' }}">Activity</a>
            <a href="{{ route('user.assets.index') }}" class="tab {{ request()->routeIs('user.assets.*') ? 'is-active' : '' }}">Assets</a>
        </div>
        <span class="tabsep" aria-hidden="true"></span>
        <div class="tabgroup" data-label="Move">
            <a href="{{ route('user.deposits.index') }}" class="tab {{ request()->routeIs('user.deposits.*') ? 'is-active' : '' }}">Deposit</a>
            <a href="{{ route('user.withdrawals.index') }}" class="tab {{ request()->routeIs('user.withdrawals.*') ? 'is-active' : '' }}">Withdraw</a>
            <a href="{{ route('user.transfers.index') }}" class="tab {{ request()->routeIs('user.transfers.*') ? 'is-active' : '' }}">Transfer</a>
        </div>
        <span class="tabsep" aria-hidden="true"></span>
        <div class="tabgroup" data-label="Trade">
            <a href="{{ route('user.buy.index') }}" class="tab {{ request()->routeIs('user.buy.*') ? 'is-active' : '' }}">Buy</a>
            <a href="{{ route('user.sell.index') }}" class="tab {{ request()->routeIs('user.sell.*') ? 'is-active' : '' }}">Sell</a>
            <a href="{{ route('user.swap.index') }}" class="tab {{ request()->routeIs('user.swap.*') ? 'is-active' : '' }}">Swap</a>
        </div>
        <span class="tabsep" aria-hidden="true"></span>
        <div class="tabgroup">
            <a href="{{ route('user.wallets.index') }}" class="tab {{ request()->routeIs('user.wallets.*') ? 'is-active' : '' }}">Demo wallet ID</a>
            <a href="{{ route('user.profile.index') }}" class="tab {{ request()->routeIs('user.profile.*') ? 'is-active' : '' }}">Profile</a>
        </div>
    </nav>
</header>

<main class="canvas">
    <div class="page-head">
        <h1>@yield('page-title', 'Overview')</h1>
        @hasSection('page-sub')<p>@yield('page-sub')</p>@endif
    </div>

    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</main>

<footer class="foot">{{ config('platform.company_full_name') }} · Demo ledger — no real funds. Stripe test mode only.</footer>

<!-- Layout bootstrap: hydrate username + show admin link if applicable -->
<script>
(async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) return;

    try {
        const response = await fetch("{{ url('api/v1/me') }}", {
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        if (!response.ok) return;

        const result = await response.json();
        if (!result.success || !result.data) return;

        // Hydrate sidebar username from whichever identity is active.
        const user = result.data.user;
        const wallet = result.data.wallet;

        const usernameEl = document.getElementById('sidebar-username');
        if (usernameEl) {
            if (user?.username) {
                usernameEl.textContent = user.username;
            } else if (wallet?.fingerprint) {
                usernameEl.textContent = wallet.fingerprint.slice(0, 12) + '…';
            }
        }

        // Show the Admin Panel link for users with the admin flag.
        if (user?.admin === true) {
            const adminLink = document.getElementById('admin-panel-link');
            if (adminLink) {
                adminLink.href = "{{ route('admin.dashboard') }}";
                adminLink.style.display = 'inline-flex';
            }
        }
    } catch (e) {
        // Non-fatal — layout hydration is best-effort.
    }
})();
</script>

<!-- 💡 NEW: JavaScript Execution Engine to manage secure API token destruction -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const logoutForm = document.getElementById('sidebar-logout-form');
    if (logoutForm) {
        logoutForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = localStorage.getItem('auth_token');
            
            if (!token) {
                localStorage.removeItem('auth_token');
                window.location.href = "{{ route('wallet.view.generate') }}";
                return;
            }

            try {
                // Call your unified api.php logout route matrix
                await fetch("{{ url('api/v1/logout') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token
                    }
                });
            } catch (error) {
                console.error('API backend token deletion failure context:', error);
            } finally {
                // Wipe local properties and snap the user back to the entry panel setup layout
                localStorage.removeItem('auth_token');
                window.location.href = "{{ route('wallet.view.generate') }}";
            }
        });
    }
});
</script>

@stack('scripts')
</body>
</html>
