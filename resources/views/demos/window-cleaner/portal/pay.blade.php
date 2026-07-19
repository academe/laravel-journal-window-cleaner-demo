@extends('demos.window-cleaner.layout')
@section('title', 'Pay online')
@section('content')
    @php use Academe\LaravelJournal\Support\MoneyFormatter; @endphp
    <h1>Pay online</h1>
    <p>Acting as <strong>{{ $customer->name }}</strong>.
    @if ($owed->isPositive()) You currently owe {{ MoneyFormatter::format($owed) }}. @endif
    Pay any amount — more than you owe puts your account in credit; less
    reduces what you owe. (No card details: this is an emulation.)</p>

    <form class="stack" method="post" action="{{ route('wc.portal.pay.store') }}">
        @csrf
        <label for="amount">Amount ({{ config('demo.currency') }})</label>
        <input id="amount" name="amount" inputmode="decimal" placeholder="e.g. 10.00" value="{{ old('amount', $suggested) }}" required>
        @error('amount')<small class="owes">{{ $message }}</small>@enderror
        <button>Pay now</button>
    </form>
@endsection
