<!DOCTYPE html>
<html lang="en" class="dark public-html scroll-smooth" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">

    {{-- SEO & Metadata --}}
    <title>@yield('title', 'Entrepreneurs Automation — Enterprise Commerce & Intelligence Platform')</title>
    <meta name="description" content="@yield('meta_description', 'Your Business. Connected. Intelligent. Automated. Unify customer conversations, multi-source business knowledge, and intelligent automation into one enterprise ecosystem.')">
    <meta name="keywords" content="business automation, multi-source AI, omni-channel chatbot, customer support AI, business intelligence, excel AI grounding, messenger automation">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph / Social Sharing --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'Entrepreneurs Automation — Enterprise Commerce & Intelligence Platform')">
    <meta property="og:description" content="@yield('meta_description', 'Your Business. Connected. Intelligent. Automated. Grounded business knowledge and customer engagement.')">
    <meta property="og:image" content="{{ asset('images/logo/ea-logo-full-dark.png') }}">
    <meta property="og:site_name" content="Entrepreneurs Automation">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Entrepreneurs Automation')">
    <meta name="twitter:description" content="@yield('meta_description', 'Your Business. Connected. Intelligent. Automated.')">
    <meta name="twitter:image" content="{{ asset('images/logo/ea-logo-full-dark.png') }}">

    {{-- Zero-Flicker Dark/Light Theme Engine --}}
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

    {{-- CSRF Token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Favicon --}}
    @include('admin.partials.favicon')

    {{-- Typography & Icons --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">

    {{-- Compiled Styles --}}
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

        /* Enterprise Chamfer Card & Subtle Border Tone */
        .chamfer-card {
            border: 1px solid var(--color-border);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03), 0 1px 2px -1px rgba(0, 0, 0, 0.02);
        }
        html.dark .chamfer-card {
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, 0.06), 0 8px 24px -8px rgba(0, 0, 0, 0.35);
        }

        /* Button Shimmer / Border Beam reflection */
        .btn-shimmer {
            position: relative;
            overflow: hidden;
        }
        .btn-shimmer::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20%;
            height: 200%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.28), transparent);
            transform: rotate(30deg);
            transition: all 0.65s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            pointer-events: none;
        }
        .btn-shimmer:hover::after {
            left: 140%;
            opacity: 1;
        }
    </style>

    @stack('custom-styles')
</head>
<body class="public-body bg-light-canvas text-light-text dark:bg-dark-canvas dark:text-dark-text font-sans antialiased selection:bg-brand-500 selection:text-white min-h-screen flex flex-col transition-colors duration-200 overflow-x-hidden overflow-y-auto">

    {{-- Background Glow & Grid Canvas --}}
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        {{-- Subtle Radial Glow (Linear/Stripe style) --}}
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[800px] h-[450px] bg-brand-500/10 dark:bg-brand-500/15 rounded-full blur-[140px]"></div>
        <div class="absolute top-[600px] -right-40 w-[600px] h-[400px] bg-sky-500/5 dark:bg-sky-500/10 rounded-full blur-[140px]"></div>
        {{-- Micro Grid Pattern --}}
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f00a_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f00a_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,#1e293b1f_1px,transparent_1px),linear-gradient(to_bottom,#1e293b1f_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)]"></div>
    </div>

    {{-- Navigation Header --}}
    @include('components.public.header')

    {{-- Main Page Content --}}
    <main class="flex-1 relative z-10">
        @yield('content')
    </main>

    {{-- Footer --}}
    @include('components.public.footer')

    {{-- Global Script Helpers (Theme Toggle, Mobile Menu, Toast) --}}
    <script>
        function togglePublicTheme() {
            var currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            if (newTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            localStorage.setItem('agy_admin_theme', newTheme);
            updateThemeIcons();
        }

        function updateThemeIcons() {
            var theme = document.documentElement.getAttribute('data-theme') || 'dark';
            var icons = document.querySelectorAll('.public-theme-icon');
            icons.forEach(function(icon) {
                if (theme === 'dark') {
                    icon.className = 'ri-sun-line text-amber-400 public-theme-icon';
                } else {
                    icon.className = 'ri-moon-line text-slate-700 public-theme-icon';
                }
            });
        }

        function toggleMobileMenu() {
            var menu = document.getElementById('publicMobileMenu');
            var icon = document.getElementById('mobileMenuIcon');
            if (menu) {
                var isHidden = menu.classList.contains('hidden');
                if (isHidden) {
                    menu.classList.remove('hidden');
                    if (icon) icon.className = 'ri-close-line text-lg';
                } else {
                    menu.classList.add('hidden');
                    if (icon) icon.className = 'ri-menu-3-line text-lg';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateThemeIcons();
        });
    </script>

    @stack('custom-scripts')
</body>
</html>
