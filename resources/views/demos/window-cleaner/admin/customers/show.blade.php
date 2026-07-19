@extends('demos.window-cleaner.layout')
@section('title', $customer->name)
@section('content')
    @php use Academe\LaravelJournal\Support\MoneyFormatter; @endphp
    <h1>{{ $customer->name }}</h1>
    <p>{{ $customer->address }} · {{ $customer->phone }}</p>
    @php $balance = $customer->balance(); @endphp
    <p class="big {{ $balance->isNegative() ? 'owes' : 'credit' }}">
        {{ $balance->isNegative() ? 'Owes '.MoneyFormatter::format($balance->absolute()) : 'In credit '.MoneyFormatter::format($balance) }}
    </p>

    <h2>Services</h2>
    <table>
        <tr><th>Service</th><th class="num">Price</th><th class="num">Every</th><th>Next due</th><th>Active</th></tr>
        @foreach ($plans as $plan)
            <tr>
                <td>{{ $plan->service->name }}</td>
                <td class="num">{{ MoneyFormatter::format($plan->priceAsMoney()) }}</td>
                <td class="num">{{ $plan->interval_weeks }}w</td>
                <td>{{ $plan->next_due_on->toFormattedDateString() }}</td>
                <td>{{ $plan->active ? 'yes' : 'no' }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Record an ad-hoc visit</h2>
    <form class="stack" method="post" action="{{ route('wc.admin.visits.store', $customer) }}">
        @csrf
        <select name="service_id" required>
            @foreach ($services as $service)
                <option value="{{ $service->id }}">{{ $service->name }}</option>
            @endforeach
        </select>
        <input name="price" inputmode="decimal" placeholder="Price inc. VAT, e.g. 15.00" required>
        @error('price')<small class="owes">{{ $message }}</small>@enderror
        <button>Charge visit</button>
    </form>

    <h2>Record a manual payment</h2>
    <form class="stack" method="post" action="{{ route('wc.admin.payments.store', $customer) }}">
        @csrf
        <input name="amount" inputmode="decimal" placeholder="Amount, e.g. 10.00" required>
        @error('amount')<small class="owes">{{ $message }}</small>@enderror
        <button>Record payment</button>
    </form>

    <h2>Statement</h2>
    <table>
        <tr><th>Date</th><th>Memo</th><th>Tags</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">of which VAT</th><th class="num">Balance</th></tr>
        @foreach ($statement as $row)
            <tr>
                <td>{{ $row['transaction']->post_date->toFormattedDateString() }}</td>
                <td>{{ $row['transaction']->memo }}</td>
                <td>@foreach ($row['transaction']->tags as $key => $value)<small class="tag">{{ $key }}={{ $value }}</small>@endforeach</td>
                <td class="num">{{ $row['transaction']->debit ? MoneyFormatter::format($row['transaction']->debit) : '' }}</td>
                <td class="num">{{ $row['transaction']->credit ? MoneyFormatter::format($row['transaction']->credit) : '' }}</td>
                <td class="num">{{ $row['vat'] ? MoneyFormatter::format($row['vat']) : '' }}</td>
                <td class="num">{{ MoneyFormatter::format($row['running']) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
