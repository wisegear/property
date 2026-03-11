@extends('layouts.app')

@section('content')
@php
    $insightBadgeClasses = [
        'price_spike' => 'bg-amber-500 text-white border-amber-500',
        'demand_collapse' => 'bg-rose-500 text-white border-rose-500',
        'sector_outperformance' => 'bg-lime-600 text-white border-lime-600',
        'momentum_reversal' => 'bg-sky-600 text-white border-sky-600',
    ];
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="relative z-0 flex flex-col items-center justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white p-8 shadow-sm lg:flex-row">
        @include('partials.hero-background')
        <div class="max-w-4xl relative z-10">
            <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white/80 px-3 py-1 text-xs text-zinc-700 shadow-sm">
                <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                Property Insights
            </div>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">Property Market Insights</h1>
            <p class="mt-3 text-base leading-7 text-zinc-600">
                Recent postcode and sector-level market signals generated from pricing anomalies, transaction drops,
                and relative outperformance against the national HPI trend.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-zinc-600">
                <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/80 px-3 py-1">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Four anomaly types
                </span>
                <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/80 px-3 py-1">
                    <span class="h-2 w-2 rounded-full bg-zinc-400"></span>
                    Searchable by area and insight text
                </span>
            </div>
        </div>

        <div class="mt-6 lg:mt-0 lg:ml-8 flex-shrink-0 relative z-10">
            <img src="{{ asset('/assets/images/site/property-insghts.jpg') }}" alt="Property market insights" class="w-82 h-auto">
        </div>
    </section>

    <div class="mx-auto max-w-7xl py-6 sm:px-2 lg:px-0">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="order-2 lg:order-1">
                <div class="mb-4 flex flex-col gap-1 rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Showing (Alphabetical Order)</p>
                        <p class="mt-1 text-sm text-zinc-700">
                            {{ $selectedType !== '' ? ($insightTypes[$selectedType] ?? 'Filtered Insights') : 'All insight types' }}
                            @if ($search !== '')
                                for “{{ $search }}”
                            @endif
                        </p>
                    </div>
                    <p class="text-sm text-zinc-500">{{ $query->total() }} results</p>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @forelse ($query as $insight)
                        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Postcode Area</p>
                                    <h2 class="mt-1 text-xl font-semibold text-zinc-900">{{ $insight->area_code }}</h2>
                                </div>

                                <div class="sm:text-right">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Transactions</p>
                                    <p class="mt-1 text-sm font-medium text-zinc-800">{{ $insight->transactions }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Insight</p>
                                    <p class="mt-2">
                                        <span class="{{ $insightBadgeClasses[$insight->insight_type] ?? 'border-zinc-200 bg-zinc-100 text-zinc-800' }} inline-flex items-center rounded-full border px-3 py-1 text-xs tracking-wide">
                                            {{ $insightTypes[$insight->insight_type] ?? str_replace('_', ' ', $insight->insight_type) }}
                                        </span>
                                    </p>
                                    <p class="mt-1 text-sm text-zinc-700">{{ $insight->insight_text }}</p>
                                </div>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Period</p>
                                    <p class="mt-1 text-sm text-zinc-700">
                                        {{ optional($insight->period_start)->format('d M Y') }} to {{ optional($insight->period_end)->format('d M Y') }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-center text-sm text-zinc-500 shadow-sm">
                            No market insights found.
                        </div>
                    @endforelse
                </div>

                @if ($query->hasPages())
                    <div class="mt-6 flex justify-center">
                        {{ $query->links() }}
                    </div>
                @endif
            </div>

            <aside class="order-1 h-fit rounded-2xl border border-zinc-200 bg-zinc-50 p-5 shadow-sm lg:order-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Browse</p>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-900">Filter insights</h2>
                    <p class="mt-2 text-sm text-zinc-600">Search by area or jump straight to a specific anomaly type.</p>
                </div>

                <form action="{{ route('insights.search') }}" method="GET" class="mt-5 flex flex-col gap-3">
                    @if ($selectedType !== '')
                        <input type="hidden" name="type" value="{{ $selectedType }}">
                    @endif

                    <label for="insight-search" class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Search</label>
                    <input
                        id="insight-search"
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Area code or insight text"
                        class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 shadow-sm focus:border-lime-500 focus:outline-none focus:ring-2 focus:ring-lime-200"
                    >
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl border border-lime-600 bg-lime-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-lime-400"
                    >
                        Search
                    </button>
                </form>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Insight types</p>
                    <nav class="mt-3 flex flex-col gap-2">
                        <a
                            href="{{ route('insights.index', array_filter(['search' => $search])) }}"
                            class="{{ $selectedType === '' ? 'border-lime-300 bg-lime-50 text-lime-900' : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:text-zinc-900' }} rounded-xl border px-4 py-3 text-sm font-medium shadow-sm transition"
                        >
                            All Insights
                        </a>

                        @foreach ($insightTypes as $type => $label)
                        <a
                            href="{{ route('insights.search', array_filter(['type' => $type, 'search' => $search])) }}"
                            class="{{ $selectedType === $type ? (($insightBadgeClasses[$type] ?? 'border-lime-300 bg-lime-50 text-lime-900').' shadow-sm') : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:text-zinc-900' }} rounded-xl border px-4 py-3 text-sm font-medium transition"
                        >
                            {{ $label }}
                        </a>
                        @endforeach
                    </nav>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection
