@extends('layouts.app')

@section('title', 'Schools | PropertyResearch.uk')
@section('description', 'Browse school profiles on PropertyResearch, including school details, Ofsted information and nearby property context.')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="mt-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-lg md:p-8">
        <h1 class="text-2xl font-semibold text-zinc-700 md:text-3xl">Schools</h1>
        <p class="mt-2 max-w-3xl text-sm text-zinc-600">
            School profiles combine Department for Education establishment details, Ofsted inspection outcomes and nearby PropertyResearch context.
        </p>
    </section>

    <section class="rounded border border-zinc-200 bg-white p-4 shadow-lg">
        <h2 class="mb-4 text-lg font-bold text-zinc-600">School directory</h2>
        @if($schools->isEmpty())
            <p class="text-sm text-zinc-600">No schools found.</p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach($schools as $school)
                    <a href="{{ $school->url }}" class="rounded border border-zinc-200 bg-zinc-50 p-3 hover:border-lime-300">
                        <div class="break-words text-sm font-semibold text-zinc-900">{{ $school->name }}</div>
                        <div class="mt-1 text-xs text-zinc-500">
                            {{ collect([$school->phase, $school->type, $school->place])->filter()->join(' · ') }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
