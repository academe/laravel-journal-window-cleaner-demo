@extends('demos.window-cleaner.layout')
@section('title', 'Choose a customer')
@section('content')
    <h1>Customer portal — emulated login</h1>
    <p>There's no real authentication in this demo. Pick a customer to see their
    portal exactly as they would after logging in.</p>
    <table>
        @foreach ($customers as $customer)
            <tr>
                <td>{{ $customer->name }}</td>
                <td>{{ $customer->address }}</td>
                <td><form method="post" action="{{ route('wc.portal.switch.store', $customer) }}">@csrf<button class="plain">Act as</button></form></td>
            </tr>
        @endforeach
    </table>
@endsection
