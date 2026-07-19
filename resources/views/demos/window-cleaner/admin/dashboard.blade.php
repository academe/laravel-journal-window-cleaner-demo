@extends('demos.window-cleaner.layout')
@section('title', 'Admin dashboard')
@section('content')
    @php use Academe\LaravelJournal\Support\MoneyFormatter; @endphp
    <h1>Dashboard</h1>
    <div class="cards">
        <div><h2>Owed by customers</h2><p class="big owes">{{ MoneyFormatter::format($totalOwed) }}</p>
            <p><a href="{{ route('wc.admin.customers.index') }}">Customers</a></p></div>
        <div><h2>Bank balance</h2><p class="big">{{ MoneyFormatter::format($bankBalance) }}</p>
            <p><a href="{{ route('wc.admin.books') }}">The books</a></p></div>
        <div><h2>Visits due</h2><p class="big">{{ $duePlans->count() }}</p>
            <form method="post" action="{{ route('wc.admin.run-visits') }}">@csrf<button>Run due visits</button></form></div>
    </div>

    <h2>Due today or overdue</h2>
    <table>
        <tr><th>Due</th><th>Customer</th><th>Service</th><th class="num">Price</th><th class="num">Every</th></tr>
        @forelse ($duePlans as $plan)
            <tr>
                <td>{{ $plan->next_due_on->toFormattedDateString() }}</td>
                <td><a href="{{ route('wc.admin.customers.show', $plan->customer) }}">{{ $plan->customer->name }}</a></td>
                <td>{{ $plan->service->name }}</td>
                <td class="num">{{ MoneyFormatter::format($plan->priceAsMoney()) }}</td>
                <td class="num">{{ $plan->interval_weeks }}w</td>
            </tr>
        @empty
            <tr><td colspan="5">Nothing due — the round is up to date.</td></tr>
        @endforelse
    </table>

    <p>
        <a href="{{ route('wc.admin.purchases.index') }}">Purchases</a> ·
        <a href="{{ route('wc.admin.vat-return') }}">VAT return</a> ·
        <a href="{{ route('wc.admin.close-month.show') }}">Close month</a> ·
        <a href="{{ route('wc.admin.sms.outbox') }}">SMS outbox</a>
    </p>
@endsection
