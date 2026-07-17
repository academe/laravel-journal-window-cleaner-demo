@extends('demos.window-cleaner.layout')
@section('title', 'VAT return')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>VAT return</h1>
    <p>Read straight off the VAT journal for the quarter: credit legs are output
    VAT on sales, debit legs are input VAT on purchases. Nothing is stored — the
    report <em>is</em> the books.</p>

    @if ($report === null)
        <p>No VAT activity yet — charge a visit or record a purchase first.</p>
    @else
        <form method="get" action="{{ route('wc.admin.vat-return') }}">
            <label>Period
                <select name="quarter">
                    @foreach ($quarters as $quarter)
                        <option value="{{ $quarter }}" @selected($quarter === $report['quarter'])>{{ $quarter }}</option>
                    @endforeach
                </select>
            </label>
            <button>Show</button>
        </form>

        <div class="cards">
            <div><h2>Output VAT (sales)</h2><p class="big">{{ Gbp::format($report['outputVat']) }}</p></div>
            <div><h2>Input VAT (purchases)</h2><p class="big">{{ Gbp::format($report['inputVat']) }}</p></div>
            <div><h2>Net VAT due</h2>
                <p class="big {{ $report['netDue']->isNegative() ? 'credit' : 'owes' }}">{{ Gbp::format($report['netDue']) }}</p>
                <p>{{ $report['netDue']->isNegative() ? 'Reclaimable from HMRC.' : 'Payable to HMRC.' }}</p></div>
        </div>

        <p>{{ $report['start']->toFormattedDateString() }} – {{ $report['end']->toFormattedDateString() }}
        — this quarter is {{ $report['closed'] ? 'inside the closed (checkpointed) period' : 'still open for posting' }}.</p>

        <h2>Sales (output VAT)</h2>
        <table>
            <tr><th>Date</th><th>Details</th><th>Customer</th><th class="num">Net</th><th class="num">VAT</th></tr>
            @forelse ($report['sales'] as $row)
                <tr>
                    <td>{{ $row['date']->toFormattedDateString() }}</td>
                    <td>{{ $row['memo'] }}</td>
                    <td>{{ $row['reference']?->customer?->name }}</td>
                    <td class="num">{{ Gbp::format($row['net']) }}</td>
                    <td class="num">{{ Gbp::format($row['vat']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No sales this quarter.</td></tr>
            @endforelse
            <tr><th colspan="3">Total</th><th class="num">{{ Gbp::format($report['netSales']) }}</th><th class="num">{{ Gbp::format($report['outputVat']) }}</th></tr>
        </table>

        <h2>Purchases (input VAT)</h2>
        <table>
            <tr><th>Date</th><th>Details</th><th>Category</th><th class="num">Net</th><th class="num">VAT</th></tr>
            @forelse ($report['purchases'] as $row)
                <tr>
                    <td>{{ $row['date']->toFormattedDateString() }}</td>
                    <td>{{ $row['memo'] }}</td>
                    <td>{{ $row['reference']?->category }}</td>
                    <td class="num">{{ Gbp::format($row['net']) }}</td>
                    <td class="num">{{ Gbp::format($row['vat']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No purchases this quarter.</td></tr>
            @endforelse
            <tr><th colspan="3">Total</th><th class="num">{{ Gbp::format($report['netPurchases']) }}</th><th class="num">{{ Gbp::format($report['inputVat']) }}</th></tr>
        </table>
    @endif
@endsection
