<!DOCTYPE html>
<html lang="en" class="dark" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>@yield('title') | Entrepreneurs Automation</title>

    <!-- Zero-Flicker Tailwind Theme Engine -->
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
    <meta name="referrer" content="no-referrer">
    @include('admin.partials.favicon')
    @include('admin.partials.styles')
    @stack('custom-style')
</head>
<body class="bg-light-canvas text-light-text dark:bg-dark-canvas dark:text-dark-text antialiased min-h-screen flex flex-col font-sans selection:bg-brand-500 selection:text-white">
    {{-- Top Navigation Header --}}
    @include('admin.partials.header')

    {{-- Main Shell --}}
    <div id="main-wrapper" class="flex flex-1 overflow-hidden h-[calc(100vh-48px)]">
        {{-- Mobile Sidebar Backdrop --}}
        <div id="sidebarBackdrop" class="fixed inset-0 top-12 bg-black/50 backdrop-blur-[2px] z-40 hidden lg:hidden transition-opacity" onclick="toggleSidebar(false)"></div>

        {{-- Linear-Style Compact Sidebar --}}
        @include('admin.partials.sidebar')

        {{-- Content Area --}}
        <div class="content-wrapper flex-1 overflow-y-auto scrollbar {{ Route::is('conversations.*') ? '!p-0 !overflow-hidden h-full flex flex-col' : 'p-3 sm:p-4 lg:p-5' }}" id="fullpage">
            <main class="content-body {{ Route::is('conversations.*') ? 'w-full h-full flex-1 flex flex-col overflow-hidden' : 'max-w-7xl mx-auto w-full' }}">
                @yield('content')
            </main>
        </div>
    </div>

    @include('admin.partials.scripts')
    @stack('custom-scripts')
</body>
</html>
