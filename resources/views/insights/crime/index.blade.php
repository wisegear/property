@extends('layouts.app')

@section('title', 'Crime Insights Dashboard | PropertyResearch.uk')
@section('description', 'National and regional crime trends using the latest monthly crime data.')

@section('content')
@php
    $formatChange = function (float $value): string {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.number_format($value, 1).'%';
    };

    $changeClass = function (float $value): string {
        if ($value > 0) {
            return 'text-red-600';
        }

        if ($value < 0) {
            return 'text-emerald-600';
        }

        return 'text-zinc-900';
    };

    $badgeClass = function (float $value): string {
        if ($value > 0) {
            return 'border-red-200 bg-red-50 text-red-700';
        }

        if ($value < 0) {
            return 'border-emerald-200 bg-emerald-50 text-emerald-700';
        }

        return 'border-zinc-200 bg-zinc-50 text-zinc-700';
    };

    $trendIcons = [
        'up' => '↑',
        'down' => '↓',
        'flat' => '→',
    ];

    $sortLinks = [
        'total_desc' => route('insights.crime.index', ['sort' => 'total_desc']),
        'total_asc' => route('insights.crime.index', ['sort' => 'total_asc']),
        'change_desc' => route('insights.crime.index', ['sort' => 'change_desc']),
        'change_asc' => route('insights.crime.index', ['sort' => 'change_asc']),
    ];
    $crimeTypeSortLinks = [
        'total_desc' => route('insights.crime.index', ['crime_type_sort' => 'total_desc', 'sort' => $sort]),
        'change_desc' => route('insights.crime.index', ['crime_type_sort' => 'change_desc', 'sort' => $sort]),
        'share_desc' => route('insights.crime.index', ['crime_type_sort' => 'share_desc', 'sort' => $sort]),
    ];
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Insights / Crime
                </div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">National Crime Dashboard</h1>
                <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-600">
                    Monthly crime volumes across the latest 24 months, with national totals and area drilldowns derived from the crime dataset.
                </p>
                <div class="mt-5 flex flex-wrap gap-3 text-xs font-medium text-zinc-600">
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1">
                        Latest month: {{ $latest_month_label ?? 'No data' }}
                    </span>
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1">
                        Window: latest 12 months vs prior 12 months
                    </span>
                </div>
            </div>
            <div class="relative z-10 mt-2 flex justify-center lg:mt-0 lg:justify-end">
                <img src="{{ asset('/assets/images/site/property-insghts.jpg') }}" alt="Crime insights dashboard" class="h-auto w-full max-w-[15rem] sm:max-w-xs">
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-5 lg:grid-cols-3">
        <article class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Total Crime</p>
                    <p class="mt-3 text-3xl font-bold text-zinc-900">{{ number_format($summary['total_12m']) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">Latest 12 months</p>
                </div>
                @include('partials.trend-gauge', [
                    'value' => $summary['pct_change'],
                    'invert' => true,
                    'title' => $formatChange($summary['pct_change']),
                    'wrapperClass' => 'ml-0 h-16 w-24 sm:h-20 sm:w-28 justify-end self-stretch',
                    'svgClass' => 'h-12 w-20 sm:h-16 sm:w-24',
                ])
            </div>
            <div class="mt-5 h-14">
                <canvas id="crime-total-sparkline" class="h-full w-full"></canvas>
            </div>
        </article>

        <article class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">YoY Change</p>
                    <p class="mt-3 text-3xl font-bold {{ $changeClass($summary['pct_change']) }}">{{ $formatChange($summary['pct_change']) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ number_format($summary['prev_12m']) }} in the prior 12 months</p>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass($summary['pct_change']) }}">
                    {{ $summary['pct_change'] > 0 ? 'Higher' : ($summary['pct_change'] < 0 ? 'Lower' : 'Flat') }}
                </span>
            </div>
            <div class="mt-5 h-14">
                <canvas id="crime-yoy-sparkline" class="h-full w-full"></canvas>
            </div>
        </article>

        <article class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Last 3-Month Trend</p>
                    <p class="mt-3 text-3xl font-bold {{ $changeClass($summary['last_3m_change']) }}">{{ $formatChange($summary['last_3m_change']) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ number_format($summary['last_3m_total']) }} vs {{ number_format($summary['prev_3m_total']) }}</p>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass($summary['last_3m_change']) }}">
                    Recent momentum
                </span>
            </div>
            <div class="mt-5 h-14">
                <canvas id="crime-recent-sparkline" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Key Headlines</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">What stands out right now</h2>
            <div class="mt-5 grid gap-3">
                @forelse ($headlines as $headline)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700">{{ $headline }}</div>
                @empty
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">No crime headlines are available yet.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Monthly Trend</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">Crime volume by month</h2>
                    <p class="mt-2 text-sm text-zinc-600">Monthly crime compared to the same period last year</p>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Latest 12 months vs previous 12 months</span>
            </div>
            <div class="mt-6 h-72">
                <canvas id="crime-monthly-chart" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Crime Composition</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Crime composition and trends</h2>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <a href="{{ $crimeTypeSortLinks['total_desc'] }}" class="rounded-full border px-3 py-1 {{ $crime_type_sort === 'total_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Total desc</a>
                        <a href="{{ $crimeTypeSortLinks['change_desc'] }}" class="rounded-full border px-3 py-1 {{ $crime_type_sort === 'change_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Change desc</a>
                        <a href="{{ $crimeTypeSortLinks['share_desc'] }}" class="rounded-full border px-3 py-1 {{ $crime_type_sort === 'share_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Share desc</a>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Crime Type</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Total 12m</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">YoY Change</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Share %</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($crime_types as $crimeType)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-6 py-4 font-medium text-zinc-900">{{ $crimeType['type'] }}</td>
                                <td class="px-6 py-4 text-zinc-700">{{ number_format($crimeType['total_12m']) }}</td>
                                <td class="px-6 py-4 font-semibold {{ $changeClass($crimeType['yoy_change']) }}">{{ $formatChange($crimeType['yoy_change']) }}</td>
                                <td class="px-6 py-4 text-zinc-700">{{ number_format($crimeType['share_pct'], 1) }}%</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass($crimeType['yoy_change']) }}">
                                        <span>
                                            {{ $crimeType['trend'] === 'Up' ? '↑' : ($crimeType['trend'] === 'Down' ? '↓' : '→') }}
                                        </span>
                                        <span>{{ $crimeType['trend'] }}</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-zinc-600">No national crime type breakdown is available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Regional Drilldowns</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Area comparison</h2>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <a href="{{ $sortLinks['total_desc'] }}" class="rounded-full border px-3 py-1 {{ $sort === 'total_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Total desc</a>
                        <a href="{{ $sortLinks['total_asc'] }}" class="rounded-full border px-3 py-1 {{ $sort === 'total_asc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Total asc</a>
                        <a href="{{ $sortLinks['change_desc'] }}" class="rounded-full border px-3 py-1 {{ $sort === 'change_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Change desc</a>
                        <a href="{{ $sortLinks['change_asc'] }}" class="rounded-full border px-3 py-1 {{ $sort === 'change_asc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Change asc</a>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Area</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Total 12m</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">YoY change</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($areas as $area)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-6 py-4 font-medium text-zinc-900">
                                    <a href="{{ route('insights.crime.show', ['area' => $area['slug']]) }}" class="hover:text-lime-700">{{ $area['area'] }}</a>
                                </td>
                                <td class="px-6 py-4 text-zinc-700">{{ number_format($area['total_12m']) }}</td>
                                <td class="px-6 py-4 font-semibold {{ $changeClass($area['pct_change']) }}">{{ $formatChange($area['pct_change']) }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass($area['pct_change']) }}">
                                        <span>{{ $trendIcons[$area['trend']] ?? '→' }}</span>
                                        <span>{{ ucfirst($area['trend']) }}</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-zinc-600">No regional crime aggregates are available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>

</div>

<script>
    const crimeChartData = {{ \Illuminate\Support\Js::from($chart) }};
    const crimeSummary = {{ \Illuminate\Support\Js::from($summary) }};
    const sparklineTail = crimeChartData.current_year.slice(-12);
    const comparisonTail = crimeChartData.previous_year.slice(-12);
    const recentTail = crimeChartData.current_year.slice(-6);
    const crimeDirectionColor = (change) => {
        if (change > 0) {
            return '#dc2626';
        }

        if (change < 0) {
            return '#16a34a';
        }

        return '#2563eb';
    };
    const sparklineTrendColor = (series) => {
        if (series.length < 2) {
            return '#2563eb';
        }

        const change = series[series.length - 1] - series[0];

        return crimeDirectionColor(change);
    };

    const sparklineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: { x: { display: false }, y: { display: false } },
        elements: { point: { radius: 0 } },
    };

    const makeSparkline = (id, data, color) => {
        const canvas = document.getElementById(id);

        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.map((_, index) => index + 1),
                datasets: [{
                    data,
                    borderColor: color,
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    tension: 0.35,
                }],
            },
            options: sparklineOptions,
        });
    };

    makeSparkline('crime-total-sparkline', sparklineTail, sparklineTrendColor(sparklineTail));
    makeSparkline('crime-yoy-sparkline', comparisonTail, crimeDirectionColor(crimeSummary.pct_change));
    makeSparkline('crime-recent-sparkline', recentTail, crimeDirectionColor(crimeSummary.last_3m_change));

    const monthlyCanvas = document.getElementById('crime-monthly-chart');

    if (monthlyCanvas) {
        new Chart(monthlyCanvas, {
            type: 'line',
            data: {
                labels: crimeChartData.labels,
                datasets: [
                    {
                        label: 'Latest 12 months',
                        data: crimeChartData.current_year,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        fill: true,
                        borderWidth: 3,
                        tension: 0.28,
                    },
                    {
                        label: 'Previous 12 months',
                        data: crimeChartData.previous_year,
                        borderColor: '#94a3b8',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [8, 6],
                        tension: 0.2,
                        pointRadius: 0,
                        fill: false,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    }
</script>
@endsection
