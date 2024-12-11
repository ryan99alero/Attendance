<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Application')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<nav>
    <!-- Navigation content -->
</nav>

<main class="container">
    @yield('content')
</main>

<footer>
    <!-- Footer content -->
</footer>

<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
