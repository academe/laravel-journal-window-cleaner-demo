@extends('demos.window-cleaner.layout')
@section('title', 'My account')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>My account</h1>
    <p>Acting as <strong>{{ $customer->name }}</strong> — <a href="{{ route('wc.portal.switch') }}">switch</a></p>

    @php $balance = $customer->balance(); @endphp
    @if ($balance->isNegative())
        <p class="big owes">You owe {{ Gbp::format($balance->absolute()) }}</p>
        <p><a href="{{ route('wc.portal.pay') }}"><strong>Pay online</strong></a></p>
    @elseif ($balance->isPositive())
        <p class="big credit">You are {{ Gbp::format($balance) }} in credit</p>
    @else
        <p class="big">Your balance is settled — nothing to pay.</p>
    @endif

    <h2>Your services</h2>
    <table>
        <tr><th>Service</th><th class="num">Price</th><th>Every</th><th>Next visit</th></tr>
        @foreach ($plans as $plan)
            <tr>
                <td>{{ $plan->service->name }}</td>
                <td class="num">{{ Gbp::format($plan->priceAsMoney()) }}</td>
                <td>{{ $plan->interval_weeks }} week(s)</td>
                <td>{{ $plan->next_due_on->toFormattedDateString() }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Statement</h2>
    <table>
        <tr><th>Date</th><th>Detail</th><th class="num">Amount</th><th class="num">Balance</th></tr>
        @foreach ($statement as $row)
            <tr>
                <td>{{ $row['transaction']->post_date->toFormattedDateString() }}</td>
                <td>{{ $row['transaction']->memo }}</td>
                <td class="num">{{ Gbp::format($row['transaction']->amount) }}</td>
                <td class="num">{{ Gbp::format($row['running']) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
