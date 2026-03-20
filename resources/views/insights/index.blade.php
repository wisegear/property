@extends('layouts.app')

@section('content')
@php
    $insightBadgeClasses = [
        'price_spike' => 'bg-orange-500 text-white border-orange-500',
        'price_collapse' => 'bg-red-500 text-white border-red-500',
        'demand_collapse' => 'bg-red-500 text-white border-red-500',
        'liquidity_stress' => 'bg-amber-600 text-white border-amber-600',
        'liquidity_surge' => 'bg-green-500 text-white border-green-500',
        'market_freeze' => 'bg-red-600 text-white border-red-600',
        'sector_outperformance' => 'bg-green-600 text-white border-green-600',
        'momentum_reversal' => 'bg-blue-500 text-white border-blue-500',
        'unexpected_hotspot' => 'bg-orange-600 text-white border-orange-600',
    ];
    $insightTypeGroups = [
        'Price Signals' => ['price_spike', 'price_collapse'],
        'Market Activity' => ['liquidity_stress', 'liquidity_surge', 'demand_collapse', 'market_freeze'],
        'Market Trends' => ['sector_outperformance', 'momentum_reversal', 'unexpected_hotspot'],
    ];
    $insightTypeChartLabels = array_values($insightTypes);
    $insightTypeChartValues = collect(array_keys($insightTypes))
        ->map(fn (string $type): int => (int) ($insightTypeCounts[$type] ?? 0))
        ->values()
        ->all();
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
            <p class="mt-3 text-sm leading-6 text-zinc-600">
                <span class="font-semibold text-zinc-800">Price Spike</span> highlights unusually strong median price growth, <span class="font-semibold text-zinc-800">Liquidity Stress</span> flags falling transaction volumes despite rising prices, <span class="font-semibold text-zinc-800">Sector Outperformance</span> shows sectors rising faster than the national HPI trend, and <span class="font-semibold text-zinc-800">Momentum Reversal</span> identifies areas where earlier strong price growth has turned into decline.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-zinc-600">
                <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/80 px-3 py-1">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Nine anomaly types
                </span>
                <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/80 px-3 py-1">
                    <span class="h-2 w-2 rounded-full bg-zinc-400"></span>
                    Searchable by area and insight text
                </span>
                @if ($lastRunAt)
                    <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/80 px-3 py-1">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        Last run {{ $lastRunAt->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                    </span>
                @endif
            </div>
        </div>

        <div class="mt-6 lg:mt-0 lg:ml-8 flex-shrink-0 relative z-10">
            <img src="{{ asset('/assets/images/site/property-insghts.jpg') }}" alt="Property market insights" class="w-82 h-auto">
        </div>
    </section>

    <section class="mt-6">
        <details class="group rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Guide</p>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-900">What the nine insights mean</h2>
                    <p class="mt-1 text-sm text-zinc-600">Open this panel for a plain-language explanation of each market signal.</p>
                </div>
                <span class="inline-flex items-center rounded-full border border-zinc-300 bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-700 transition group-open:rotate-180">
                    ˅
                </span>
            </summary>

            <div class="border-t border-zinc-200 px-6 py-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($insightTypes as $type => $label)
                        <article class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                            <span class="{{ $insightBadgeClasses[$type] ?? 'border-zinc-200 bg-zinc-100 text-zinc-800' }} inline-flex items-center rounded-full border px-3 py-1 text-xs tracking-wide">
                                {{ $label }}
                            </span>
                            <p class="mt-3 text-sm leading-6 text-zinc-600">
                                {{ $insightDescriptions[$type] ?? 'This signal highlights a notable change in local market conditions over the latest rolling 12-month comparison.' }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </div>
        </details>
    </section>

    <section class="mt-6">
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Signal Mix</p>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-900">Stored insights by type</h2>
                    <p class="mt-2 text-sm text-zinc-600">A quick view of how the nine anomaly types are currently distributed.</p>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">All 9 signals</span>
            </div>

            <div class="mt-5 h-72 min-w-0 overflow-hidden sm:h-80">
                <canvas id="insight-type-count-chart" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>
    </section>

    <div class="mx-auto max-w-7xl py-6 sm:px-2 lg:px-0">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="order-2 lg:order-1">
                <div class="mb-4 flex flex-col gap-1 rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <form action="{{ route('insights.index', request()->query()) }}" method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @if ($search !== '')
                                <input type="hidden" name="search" value="{{ $search }}">
                            @endif
                            @if ($selectedType !== '')
                                <input type="hidden" name="type" value="{{ $selectedType }}">
                            @endif
                            <label for="insights-sort" class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Showing</label>
                            <select
                                id="insights-sort"
                                name="sort"
                                onchange="this.form.submit()"
                                class="min-w-44 rounded-full border border-zinc-300 bg-zinc-50 px-4 py-2 text-xs font-semibold text-zinc-700 shadow-sm focus:border-lime-500 focus:outline-none focus:ring-2 focus:ring-lime-200"
                            >
                                <option value="sector_asc" @selected($sort === 'sector_asc')>Postcode (A-Z)</option>
                                <option value="sector_desc" @selected($sort === 'sector_desc')>Postcode (Z-A)</option>
                                <option value="transactions_desc" @selected($sort === 'transactions_desc')>Most transactions</option>
                                <option value="transactions_asc" @selected($sort === 'transactions_asc')>Fewest transactions</option>
                            </select>
                        </form>
                    </div>
                    <span class="text-sm font-semibold text-gray-700">{{ $query->total() }} insight signals</span>
                </div>

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    @forelse ($query as $insight)
                        <a
                            href="{{ route('insights.show', ['sector' => strtolower($insight->area_code)]) }}"
                            class="flex h-full min-h-[240px] flex-col rounded-xl border border-zinc-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-lime-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-lime-300"
                        >
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <span class="{{ $insightBadgeClasses[$insight->insight_type] ?? 'border-zinc-200 bg-zinc-100 text-zinc-800' }} inline-flex items-center rounded-full border px-3 py-1 text-xs tracking-wide">
                                    {{ $insightTypes[$insight->insight_type] ?? str_replace('_', ' ', $insight->insight_type) }}
                                </span>

                                <p class="text-sm font-semibold text-gray-700">{{ $insight->area_code }} • {{ $insight->transactions }} sales</p>
                            </div>

                            <div class="flex flex-1 flex-col gap-4">
                                <div>
                                    <p class="text-sm leading-6 text-zinc-700">{{ $insight->insight_text }}</p>
                                </div>

                                <div class="mt-auto">
                                    <div class="flex items-end justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Rolling Period</p>
                                            <p class="mt-1 text-sm text-zinc-700">
                                                {{ optional($insight->period_start)->format('d M Y') }} &rarr; {{ optional($insight->period_end)->format('d M Y') }}
                                            </p>
                                        </div>

                                        <span class="inline-flex items-center rounded-full border border-zinc-300 bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-600 transition hover:border-lime-400 hover:bg-zinc-50 hover:text-lime-700">
                                            View Data
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
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

            <div class="order-1 flex h-fit flex-col gap-6 lg:order-2">
                <aside class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Search</p>
                        <h2 class="mt-2 text-lg font-semibold text-zinc-900">Find a place or signal</h2>
                        <p class="mt-2 text-sm text-zinc-600">Search by area code or insight text.</p>
                    </div>

                    <form action="{{ route('insights.search') }}" method="GET" class="mt-5 flex flex-col gap-3">
                        @if ($selectedType !== '')
                            <input type="hidden" name="type" value="{{ $selectedType }}">
                        @endif
                        @if ($sort !== 'sector_asc')
                            <input type="hidden" name="sort" value="{{ $sort }}">
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
                </aside>

                <aside class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Browse</p>
                        <h2 class="mt-2 text-lg font-semibold text-zinc-900">Filter insights</h2>
                        <p class="mt-2 text-sm text-zinc-600">Jump straight to a specific anomaly type.</p>
                    </div>

                    <div class="mt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Insight types</p>
                        <nav class="mt-3 flex flex-col gap-2">
                            <a
                                href="{{ route('insights.index', array_filter(['search' => $search, 'sort' => $sort !== 'sector_asc' ? $sort : null])) }}"
                                class="{{ $selectedType === '' ? 'border-lime-300 bg-lime-50 text-lime-900' : 'border-zinc-200 bg-zinc-100 text-zinc-700 hover:border-zinc-300 hover:text-zinc-900' }} rounded-xl border px-4 py-3 text-sm font-medium shadow-sm transition"
                            >
                                All Insights ({{ number_format($query->total()) }})
                            </a>

                            @foreach ($insightTypeGroups as $groupLabel => $types)
                                <div class="mt-5 first:mt-0">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $groupLabel }}</p>
                                    <div class="mt-2 flex flex-col gap-2">
                                        @foreach ($types as $type)
                                            <a
                                                href="{{ route('insights.search', array_filter(['type' => $type, 'search' => $search, 'sort' => $sort !== 'sector_asc' ? $sort : null])) }}"
                                                class="{{ $selectedType === $type ? (($insightBadgeClasses[$type] ?? 'border-lime-300 bg-lime-50 text-lime-900').' shadow-sm') : 'border-zinc-200 bg-zinc-100 text-zinc-700 hover:border-zinc-300 hover:text-zinc-900' }} rounded-xl border px-4 py-3 text-sm font-medium transition"
                                            >
                                                {{ $insightTypes[$type] ?? str_replace('_', ' ', $type) }} ({{ number_format($insightTypeCounts[$type] ?? 0) }})
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </nav>
                    </div>

                    @if ($selectedType !== '' && isset($insightDescriptions[$selectedType]))
                        <div class="mt-6 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Selected signal</p>
                            <p class="mt-2 text-sm font-semibold text-zinc-900">
                                {{ $insightTypes[$selectedType] ?? str_replace('_', ' ', $selectedType) }}
                            </p>
                            <p class="mt-2 text-sm leading-6 text-zinc-600">{{ $insightDescriptions[$selectedType] }}</p>
                        </div>
                    @endif
                </aside>

                <aside class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Methodology</p>
                    <h2 class="mt-2 text-lg font-semibold text-zinc-900">How to read the signals</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600">
                        Insight periods use rolling 12-month windows based on the latest Land Registry transaction date available.
                        Each signal compares two consecutive 12-month periods rather than fixed calendar years.
                    </p>
                    <p class="mt-3 text-sm leading-6 text-zinc-600">
                        A postcode sector can show more than one signal at the same time. For example, transaction volumes can fall sharply while median prices still rise, which means Demand Collapse and Price Spike can both appear together.
                    </p>
                </aside>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    new Chart(document.getElementById('insight-type-count-chart'), {
        type: 'bar',
        data: {
            labels: @json($insightTypeChartLabels),
            datasets: [{
                label: 'Stored insights',
                data: @json($insightTypeChartValues),
                backgroundColor: [
                    'rgba(249, 115, 22, 0.78)',
                    'rgba(239, 68, 68, 0.78)',
                    'rgba(225, 29, 72, 0.78)',
                    'rgba(217, 119, 6, 0.78)',
                    'rgba(34, 197, 94, 0.78)',
                    'rgba(220, 38, 38, 0.78)',
                    'rgba(22, 163, 74, 0.78)',
                    'rgba(59, 130, 246, 0.78)',
                    'rgba(234, 88, 12, 0.78)',
                ],
                borderColor: [
                    '#ea580c',
                    '#dc2626',
                    '#be123c',
                    '#b45309',
                    '#16a34a',
                    '#b91c1c',
                    '#15803d',
                    '#2563eb',
                    '#c2410c',
                ],
                borderWidth: 1,
                borderRadius: 8,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => Number(context.parsed.y).toLocaleString('en-GB') + ' insights',
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45,
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => Number(value).toLocaleString('en-GB'),
                    },
                },
            },
        },
    });
</script>
@endpush
