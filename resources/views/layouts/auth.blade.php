<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sign in') — {{ config('platform.company_short') }} Console</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/console.css') }}">
    @stack('styles')
</head>
<body class="console console-gate">
    <div class="gate">
        <div class="gate-brand">
            @include('partials.mark')
            <div><b>{{ config('platform.company_short') }}</b><i>Operations console</i></div>
        </div>
        <div class="gate-tag">Restricted access · administrators only</div>

        @yield('content')

        <div class="gate-foot">&copy; {{ date('Y') }} {{ config('platform.company_full_name') }}</div>
    </div>
    @stack('scripts')
</body>
</html>
