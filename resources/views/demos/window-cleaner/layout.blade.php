<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Shiny & Sons') — laravel-journal demo</title>
    <link rel="stylesheet" href="/css/demo.css">
</head>
<body>
<header>
    <strong><a href="/window-cleaner">Shiny &amp; Sons</a></strong>
    <nav>
        <a href="/window-cleaner/admin">Admin</a>
        <a href="/window-cleaner/portal/account">Customer portal</a>
        <a href="/window-cleaner/tour/level-a">Tour</a>
    </nav>
</header>
<main>
    @if (session('status'))<p class="flash">{{ session('status') }}</p>@endif
    @if (session('error'))<p class="flash error">{{ session('error') }}</p>@endif
    @yield('content')
</main>
<footer>
    <p>A demo of <a href="https://github.com/academe/laravel-ledger">academe/laravel-journal</a>.
    No real money, cards, or texts are involved.</p>
</footer>
</body>
</html>
