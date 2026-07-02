<!doctype html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">

    <link href="{{ asset('assets/css/inbox.css') }}" rel="stylesheet">

    @stack('styles')

</head>

<body class="inbox-body">

@yield('content')

@stack('scripts')

</body>

</html>