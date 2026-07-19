@extends('demos.window-cleaner.layout')
@section('title', 'Payment received')
@section('content')
    @php use Academe\LaravelJournal\Support\MoneyFormatter; @endphp
    <h1>Thank you!</h1>
    <p>We received your payment of <strong>{{ MoneyFormatter::format($payment->amountAsMoney()) }}</strong>
    on {{ $payment->paid_at->toFormattedDateString() }}.</p>

    @php $balance = $customer->balance(); @endphp
    @if ($balance->isNegative())
        <p>Your remaining balance is {{ MoneyFormatter::format($balance->absolute()) }} owed.</p>
    @elseif ($balance->isPositive())
        <p>Your account is now {{ MoneyFormatter::format($balance) }} in credit.</p>
    @else
        <p>Your account is fully settled.</p>
    @endif

    <p><a href="{{ route('wc.portal.account') }}">Back to my account</a></p>
@endsection
