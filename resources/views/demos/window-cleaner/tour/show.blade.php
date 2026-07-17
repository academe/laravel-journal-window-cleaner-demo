@extends('demos.window-cleaner.layout')
@section('title', $title)
@section('content')
    <nav>
        @foreach ($pages as $slug => $page)
            @if ($slug === $current)<strong>{{ $page['title'] }}</strong>@else<a href="{{ route('wc.tour.show', $slug) }}">{{ $page['title'] }}</a>@endif
            @if (! $loop->last) · @endif
        @endforeach
        · <a href="{{ route('wc.tour.playground') }}">Playground</a>
    </nav>

    <h1>{{ $title }}</h1>
    <p>{{ $intro }}</p>
    <pre><code>{{ $code }}</code></pre>
    <p>The real thing: <code>{{ $file }}</code> — <a href="{{ $liveUrl }}">{{ $liveLabel }}</a></p>
@endsection
