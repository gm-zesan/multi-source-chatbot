<!DOCTYPE html>
<html lang="en">
<head>
    <title>@yield('title') | Entrepreneurs Automation</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    @include('admin.partials.favicon')
    @include('admin.partials.styles')
    @stack('custom-style')

     <!--[if lt IE 9]>
         <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
         <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
         <link rel='stylesheet' href="css/ie/ie8.css">
     <![endif]-->
</head>
<body style="overflow-x: hidden">
    @include('admin.partials.header')
    <div id="main-wrapper">
        @include('admin.partials.sidebar')
        <div class="content-wrapper scrollbar" id="fullpage">
            <main class="content-body">
                @yield('content')
            </main>
            {{-- @include('admin.partials.footer') --}}
        </div>
    </div>
    @include('admin.partials.scripts')
    @stack('custom-scripts')
</body>
</html>