@extends('demos.window-cleaner.layout')
@section('title', 'Playground')
@section('content')
    @php use Academe\LaravelJournal\Support\MoneyFormatter; use App\Demos\WindowCleaner\Support\Books; @endphp
    <h1>Playground — Level A in isolation</h1>
    <p>A scratch {{ Books::currencyCode() }} wallet, outside the business's books. Raw
    <code>credit()</code> / <code>debit()</code> calls, no groups, no ledgers.
    Post in the other currency to see <code>CurrencyMismatch</code> protect the journal.</p>

    <p class="big">Balance: {{ MoneyFormatter::format($balance) }}</p>

    <form class="stack" method="post" action="{{ route('wc.tour.playground.store') }}">
        @csrf
        <select name="direction"><option value="credit">Credit (add)</option><option value="debit">Debit (remove)</option></select>
        @error('direction')<small class="owes">{{ $message }}</small>@enderror
        <input name="amount" inputmode="decimal" placeholder="Amount, e.g. 10.00" required>
        @error('amount')<small class="owes">{{ $message }}</small>@enderror
        <select name="currency">
            @foreach (['GBP', 'USD'] as $code)
                <option value="{{ $code }}">{{ $code }}{{ $code === Books::currencyCode() ? '' : ' (will fail!)' }}</option>
            @endforeach
        </select>
        <input name="memo" placeholder="Memo (optional)">
        @error('memo')<small class="owes">{{ $message }}</small>@enderror
        <button>Post entry</button>
    </form>

    <h2>Entries</h2>
    <table>
        <tr><th>Date</th><th>Memo</th><th class="num">Amount</th></tr>
        @foreach ($transactions as $transaction)
            <tr>
                <td>{{ $transaction->post_date->toFormattedDateString() }}</td>
                <td>{{ $transaction->memo }}</td>
                <td class="num">{{ MoneyFormatter::format($transaction->amount) }}</td>
            </tr>
        @endforeach
    </table>
@endsection
