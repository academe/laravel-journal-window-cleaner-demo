@extends('demos.window-cleaner.layout')
@section('title', 'Close month')
@section('content')
    <h1>Close month (checkpoints)</h1>
    <p>Closing a month checkpoints every journal through {{ $target->toFormattedDateString() }}:
    cumulative totals are stored (so balance queries scan only newer entries) and the
    period behind the checkpoint is locked — back-dated postings throw
    <code>PeriodClosed</code>. See the <a href="/window-cleaner/tour/checkpoints">Checkpoints tour page</a>.</p>

    <form method="post" action="{{ route('wc.admin.close-month.store') }}">
        @csrf
        <button>Close through {{ $target->toFormattedDateString() }}</button>
    </form>

    <h2>Existing checkpoints</h2>
    @forelse ($checkpoints as $date => $group)
        <p><strong>{{ $date }}</strong> — {{ $group->count() }} journal(s) checkpointed.</p>
    @empty
        <p>No checkpoints yet.</p>
    @endforelse
@endsection
