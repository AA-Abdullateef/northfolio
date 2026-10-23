<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Wallet Setup') — {{ config('platform.company_short') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/entry.css') }}">
    @stack('styles')
</head>
<body class="entry">
    <div class="entry-split">
        <aside class="entry-aside">
            <a class="entry-brand" href="{{ url('/') }}">@include('partials.mark')<span>{{ config('platform.company_short') }}</span></a>
            <div class="entry-pitch">
                <h2>Every balance is a sum of entries.</h2>
                <p>A ledger-first demo wallet: deposits, transfers and swaps, each recorded once and auditable end to end.</p>
                <ul class="entry-points">
                    <li><b>Passwordless identity</b> from a 12-word demo phrase</li>
                    <li><b>Stripe test mode</b> deposits, no real money moves</li>
                    <li><b>Append-only journal</b> with an audit trail</li>
                </ul>
            </div>
            <div class="entry-fine">Demo platform · not a blockchain wallet</div>
        </aside>
        <main class="entry-main">
            @yield('content')
        </main>
    </div>
</body>
</html>
