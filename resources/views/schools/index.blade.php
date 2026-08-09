@extends('layouts.app')

@section('title', 'Schools | PropertyResearch.uk')
@section('description', 'Browse school profiles on PropertyResearch, including school details, Ofsted information and nearby property context.')

@section('content')
    <section class="relative z-0 -mx-6 -mt-6 overflow-hidden bg-white py-8 shadow-[0_1px_0_rgba(0,0,0,0.06)] md:py-9">
      <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[minmax(0,1fr)_minmax(280px,0.42fr)] md:gap-8">
        <div class="max-w-4xl">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500"><span class="h-2 w-2 rounded-full bg-lime-500"></span>Local Research</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">Schools</h1>
            <p class="mt-3 max-w-3xl text-base leading-7 text-zinc-600">
                School profiles combine Department for Education establishment details, Ofsted inspection outcomes and nearby PropertyResearch context.
            </p>
        </div>
        <div class="hidden justify-self-end md:block">
            <img src="{{ asset('/assets/images/site/council.jpg') }}" alt="Schools and local research" class="h-44 w-full max-w-sm object-cover [mask-image:linear-gradient(to_right,transparent,black_22%)]">
        </div>
      </div>
    </section>
<div class="mx-auto max-w-7xl px-4 py-8">

    <section class="rounded-sm border border-zinc-200 bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-lg font-bold text-zinc-600">School directory</h2>
        @if($schools->isEmpty())
            <p class="text-sm text-zinc-600">No schools found.</p>
        @else
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach($schools as $school)
                    <a href="{{ $school->url }}" class="rounded-sm border border-zinc-200 bg-zinc-50 p-3 hover:border-lime-300">
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
