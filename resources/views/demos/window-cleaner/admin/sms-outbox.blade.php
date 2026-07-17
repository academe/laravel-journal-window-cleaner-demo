@extends('demos.window-cleaner.layout')
@section('title', 'SMS outbox')
@section('content')
    <h1>SMS outbox</h1>
    <p>Messages "sent" through the demo SMS channel. In production the channel class
    would call a real provider (Twilio, Vonage, ...); here they land in a table.</p>

    <form method="post" action="{{ route('wc.admin.sms.send') }}">
        @csrf
        <button>Text balances to everyone who owes</button>
    </form>

    <div class="sms">
        @forelse ($messages as $message)
            <div class="msg">
                <div class="meta">{{ $message->customer->name }} · {{ $message->phone }} · {{ $message->sent_at->diffForHumans() }}</div>
                {{ $message->body }}
            </div>
        @empty
            <p>No messages yet.</p>
        @endforelse
    </div>
@endsection
