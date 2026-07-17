@extends('demos.window-cleaner.layout')
@section('title', 'The books')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>The books (Level C)</h1>
    <p>Every journal belongs to a typed ledger, so the whole business reads as an
    accounting equation. Each balance below is one <code>Ledger::currentBalance('GBP')</code> call.</p>

    <table>
        <tr><th>Ledger</th><th>Type</th><th class="num">Balance</th></tr>
        <tr><td>Debtors (all customer journals)</td><td>asset</td><td class="num">{{ Gbp::format($debtors) }}</td></tr>
        <tr><td>Bank</td><td>asset</td><td class="num">{{ Gbp::format($bank) }}</td></tr>
        <tr><td>Sales</td><td>income</td><td class="num">{{ Gbp::format($sales) }}</td></tr>
        <tr><td>VAT owed</td><td>liability</td><td class="num">{{ Gbp::format($vat) }}</td></tr>
        <tr><td>Expenses</td><td>expense</td><td class="num">{{ Gbp::format($expenses) }}</td></tr>
    </table>

    <p class="flash {{ $assets->equals($liabilitiesPlusIncomeLessExpenses) ? '' : 'error' }}">
        Assets {{ Gbp::format($assets) }} = liabilities + income − expenses {{ Gbp::format($liabilitiesPlusIncomeLessExpenses) }}
        — the equation {{ $assets->equals($liabilitiesPlusIncomeLessExpenses) ? 'balances' : 'DOES NOT balance' }}.
    </p>

    <h2>Recent journal entries (grouped)</h2>
    {{-- One table for every group: a single column grid keeps the Dr/Cr
         columns aligned across groups, which per-group tables cannot do. --}}
    <table>
        @foreach ($recentGroups as $uuid => $entries)
            <tr><th colspan="3">{{ $entries->first()->post_date->toFormattedDateString() }} — {{ $entries->first()->memo }}</th></tr>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->journal->displayName() }}</td>
                    <td class="num">{{ $entry->debit ? 'Dr '.Gbp::format($entry->debit) : '' }}</td>
                    <td class="num">{{ $entry->credit ? 'Cr '.Gbp::format($entry->credit) : '' }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
@endsection
