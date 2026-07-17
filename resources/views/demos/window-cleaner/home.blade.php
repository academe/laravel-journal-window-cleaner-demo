@extends('demos.window-cleaner.layout')
@section('title', 'Shiny & Sons')
@section('content')
    <h1>Shiny &amp; Sons — window cleaning</h1>
    <p>A VAT-registered window cleaning round. Customers have services at individual
    prices and schedules, hold an account balance, and can pay online. Every number
    on every page comes out of <code>academe/laravel-journal</code>.</p>
    <div class="cards">
        <div><h2><a href="/window-cleaner/admin">Admin</a></h2>
            <p>The window cleaner's side: run the round, take payments, read the books, close the month, text balances.</p></div>
        <div><h2><a href="/window-cleaner/portal/account">Customer portal</a></h2>
            <p>What a customer sees: their balance, their services, their statement, and a pay-online form.</p></div>
        <div><h2><a href="/window-cleaner/tour/level-a">Tour</a></h2>
            <p>The guided tour: which package feature powers which page, with the actual code.</p></div>
    </div>
@endsection
