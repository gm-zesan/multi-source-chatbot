<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark public-html scroll-smooth" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5" />

    <title>@yield('title', 'Sign In') — Entrepreneurs Automation</title>
    <meta name="description" content="Sign in or register for Entrepreneurs Automation, the enterprise platform for customer conversations, multi-source knowledge grounding, and commerce intelligence.">
    <meta property="og:image" content="{{ asset('images/logo/ea-logo-full-dark.png') }}">
    <meta name="twitter:image" content="{{ asset('images/logo/ea-logo-full-dark.png') }}">

    {{-- Zero-Flicker Tailwind Theme Engine --}}
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('agy_admin_theme') || 'dark';
                document.documentElement.setAttribute('data-theme', savedTheme);
                if (savedTheme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        })();
    </script>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('admin.partials.favicon')

    {{-- Fonts & Icons --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">

    {{-- Core Tailwind CSS & Vite App --}}
    @vite(['resources/css/admin.css', 'resources/js/app.js'])

    <style>
        html.public-html,
        body.public-body {
            height: auto !important;
            min-height: 100vh !important;
            max-height: none !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
    </style>

    @stack('custom-style')
</head>
<body class="public-body bg-light-canvas text-light-text dark:bg-dark-canvas dark:text-dark-text font-sans antialiased selection:bg-brand-500 selection:text-white min-h-screen flex flex-col justify-between transition-colors duration-200 overflow-x-hidden relative">

    {{-- Background Glow & Grid Canvas --}}
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[350px] bg-brand-500/10 dark:bg-brand-500/15 rounded-full blur-[140px]"></div>
        <div class="absolute -bottom-32 right-1/4 w-[500px] h-[300px] bg-sky-500/5 dark:bg-sky-500/10 rounded-full blur-[140px]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f00a_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f00a_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,#1e293b1f_1px,transparent_1px),linear-gradient(to_bottom,#1e293b1f_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)]"></div>
    </div>

    {{-- Main Auth Slot --}}
    <main class="relative z-10 flex-1 flex items-center justify-center px-4 py-8 sm:px-6">
        <div class="w-full max-w-md">
            {{ $slot }}
        </div>
    </main>

    {{-- Minimal Footer --}}
    <footer class="relative z-10 py-6 text-center text-xs text-light-muted dark:text-dark-muted">
        <div class="flex items-center justify-center gap-4 text-3xs font-mono">
            <span>&copy; {{ date('Y') }} Entrepreneurs Automation</span>
            <span class="text-light-border dark:text-dark-border">/</span>
            <a href="{{ route('privacy') }}" class="hover:underline hover:text-light-text dark:hover:text-dark-text transition-colors">Privacy</a>
            <span class="text-light-border dark:text-dark-border">/</span>
            <a href="{{ route('terms') }}" class="hover:underline hover:text-light-text dark:hover:text-dark-text transition-colors">Terms</a>
        </div>
    </footer>

    {{-- Theme Switching Script --}}
    <script>
        function toggleAuthTheme() {
            var current = document.documentElement.getAttribute('data-theme') || 'dark';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            if (next === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            localStorage.setItem('agy_admin_theme', next);
            updateAuthThemeIcons();
        }

        function updateAuthThemeIcons() {
            var theme = document.documentElement.getAttribute('data-theme') || 'dark';
            var icons = document.querySelectorAll('.auth-theme-icon');
            icons.forEach(function(icon) {
                if (theme === 'dark') {
                    icon.className = 'ri-sun-line text-amber-400 auth-theme-icon';
                } else {
                    icon.className = 'ri-moon-line text-slate-700 auth-theme-icon';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', updateAuthThemeIcons);
    </script>

    @stack('custom-scripts')
</body>
</html>
