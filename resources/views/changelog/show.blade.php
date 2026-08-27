@extends('layouts.app')

@section('title', $changeLogEntry->title . ' — Change Log')
@section('description', Str::limit($changeLogEntry->body, 155))

@section('content')
    <article class="mx-auto max-w-4xl py-8 md:py-12">
        <a href="{{ route('changelog.index') }}" class="inline-flex text-sm font-semibold text-lime-700 hover:text-lime-600">&larr; Back to Change Log</a>
        <header class="mt-8 border-b border-zinc-200 pb-8">
            <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                <span class="rounded-full px-3 py-1 font-semibold {{ $changeLogEntry->category === 'Change' ? 'bg-lime-100 text-lime-800' : 'bg-sky-100 text-sky-800' }}">{{ $changeLogEntry->category }}</span>
                <time datetime="{{ $changeLogEntry->published_at->toIso8601String() }}">{{ $changeLogEntry->published_at->format('j F Y, H:i') }}</time>
            </div>
            <h1 class="mt-5 text-4xl font-bold tracking-tight text-zinc-900 md:text-5xl">{{ $changeLogEntry->title }}</h1>
            @if ($changeLogEntry->author)
                <p class="mt-4 text-sm text-zinc-500">By {{ $changeLogEntry->author->name }}</p>
            @endif
        </header>
        <div class="wise1text mt-8 whitespace-pre-line text-zinc-700">{{ $changeLogEntry->body }}</div>
    </article>
@endsection
