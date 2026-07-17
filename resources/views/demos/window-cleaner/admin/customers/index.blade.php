@extends('demos.window-cleaner.layout')
@section('title', 'Customers')
@section('content')
    @php use App\Demos\WindowCleaner\Support\Gbp; @endphp
    <h1>Customers</h1>
    <table>
        <tr><th>Name</th><th>Address</th><th class="num">Balance</th></tr>
        @foreach ($customers as $customer)
            @php $balance = $customer->balance(); @endphp
            <tr>
                <td><a href="{{ route('wc.admin.customers.show', $customer) }}">{{ $customer->name }}</a></td>
                <td>{{ $customer->address }}</td>
                <td class="num {{ $balance->isNegative() ? 'owes' : 'credit' }}">
                    {{ $balance->isNegative() ? Gbp::format($balance->absolute()).' owed' : Gbp::format($balance).' in credit' }}
                </td>
            </tr>
        @endforeach
    </table>
@endsection
