@extends('layouts.app')

@section('title', 'Change Log')
@section('description', 'Changes and data updates across PropertyResearch.')

@section('content')
    <div class="mx-auto max-w-4xl py-8 md:py-12">
        <header class="border-b border-zinc-200 pb-8">
            <p class="text-sm font-semibold uppercase tracking-wider text-lime-700">PropertyResearch</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-zinc-900 md:text-5xl">Change Log</h1>
            <p class="mt-4 text-lg text-zinc-600">Changes and data updates across PropertyResearch.</p>
        </header>

        <div class="divide-y divide-zinc-200">
            @forelse ($entries as $entry)
                <article class="py-8">
                    <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                        <span class="rounded-full px-3 py-1 font-semibold {{ $entry->category === 'Change' ? 'bg-lime-100 text-lime-800' : 'bg-sky-100 text-sky-800' }}">{{ $entry->category }}</span>
                        <time datetime="{{ $entry->published_at->toIso8601String() }}">{{ $entry->published_at->format('j F Y') }}</time>
                        @if ($entry->author)
                            <span>by {{ $entry->author->name }}</span>
                        @endif
                    </div>
                    <h2 class="mt-4 text-2xl font-semibold tracking-tight text-zinc-900">
                        <a href="{{ route('changelog.show', $entry) }}" class="hover:text-lime-700 hover:underline">{{ $entry->title }}</a>
                    </h2>
                    <p class="mt-3 leading-7 text-zinc-600">{{ Str::limit($entry->body, 240) }}</p>
                    <a href="{{ route('changelog.show', $entry) }}" class="mt-4 inline-flex text-sm font-semibold text-lime-700 hover:text-lime-600">Read entry &rarr;</a>
                </article>
            @empty
                <p class="py-12 text-zinc-600">There are no Change Log entries yet.</p>
            @endforelse
        </div>

        <div class="mt-6">{{ $entries->links() }}</div>
    </div>
@endsection
