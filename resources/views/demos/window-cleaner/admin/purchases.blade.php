@extends('demos.window-cleaner.layout')
@section('title', 'Purchases')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>Purchases</h1>
    <p>Supplies and equipment, paid at the till. Each purchase posts one balanced
    group: net cost to Expenses, input VAT to the VAT journal (reducing what is
    owed), and the gross out of the Bank.</p>

    <form class="stack" method="post" action="{{ route('wc.admin.purchases.store') }}">
        @csrf
        <label>Supplier <input name="supplier" value="{{ old('supplier') }}" required></label>
        @error('supplier')<p class="flash error">{{ $message }}</p>@enderror
        <label>Category
            <select name="category">
                <option value="supplies" @selected(old('category') === 'supplies')>Supplies</option>
                <option value="equipment" @selected(old('category') === 'equipment')>Equipment</option>
            </select>
        </label>
        @error('category')<p class="flash error">{{ $message }}</p>@enderror
        <label>Price (£, VAT-inclusive) <input name="price" inputmode="decimal" value="{{ old('price') }}" required></label>
        @error('price')<p class="flash error">{{ $message }}</p>@enderror
        <button>Record purchase</button>
    </form>

    <h2>Recent purchases</h2>
    <table>
        <tr><th>Date</th><th>Supplier</th><th>Category</th><th class="num">Gross</th></tr>
        @forelse ($purchases as $purchase)
            <tr>
                <td>{{ $purchase->purchased_on->toFormattedDateString() }}</td>
                <td>{{ $purchase->supplier }}</td>
                <td>{{ $purchase->category }}</td>
                <td class="num">{{ Gbp::format($purchase->priceAsMoney()) }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No purchases yet.</td></tr>
        @endforelse
    </table>
@endsection
