@extends('layouts.app')

@section('title', $area.' Crime Trends | PropertyResearch.uk')
@section('description', 'Regional crime drilldown for '.$area.' using monthly crime data.')

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

    $breakdownSortLinks = [
        'impact_desc' => route('insights.crime.show', ['area' => $area_slug, 'breakdown_sort' => 'impact_desc']),
        'total_desc' => route('insights.crime.show', ['area' => $area_slug, 'breakdown_sort' => 'total_desc']),
        'yoy_desc' => route('insights.crime.show', ['area' => $area_slug, 'breakdown_sort' => 'yoy_desc']),
        'share_desc' => route('insights.crime.show', ['area' => $area_slug, 'breakdown_sort' => 'share_desc']),
    ];
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Insights / Crime / {{ $area }}
                </div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">{{ $area }} Crime Drilldown</h1>
                <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-600">
                    How crime is changing and what is driving it in {{ $area }}.
                </p>
                <div class="mt-5 flex flex-wrap gap-3 text-xs font-medium text-zinc-600">
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1">
                        Latest month: {{ $latest_month_label ?? 'No data' }}
                    </span>
                    <a href="{{ route('insights.crime.index') }}" class="inline-flex items-center rounded-full border border-lime-200 bg-lime-50 px-4 py-2 text-sm font-semibold text-lime-800 transition hover:border-lime-300 hover:text-lime-700 hover:underline">
                        Back to national dashboard
                    </a>
                </div>
            </div>
            <div class="relative z-10 flex justify-center lg:justify-end">
                <img src="{{ asset('/assets/images/site/property-insghts.jpg') }}" alt="{{ $area }} crime trends" class="h-auto w-full max-w-[15rem] sm:max-w-xs">
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-5 lg:grid-cols-3">
        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Total Crime</p>
            <p class="mt-3 text-3xl font-bold text-zinc-900">{{ number_format($summary['total_12m']) }}</p>
            <p class="mt-2 text-sm text-zinc-600">Latest 12 months</p>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">YoY Change</p>
                    <p class="mt-3 text-3xl font-bold {{ $changeClass($summary['pct_change']) }}">{{ $formatChange($summary['pct_change']) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ number_format($summary['prev_12m']) }} in the prior 12 months</p>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass($summary['pct_change']) }}">
                    {{ $summary['pct_change'] > 0 ? 'Higher' : ($summary['pct_change'] < 0 ? 'Lower' : 'Flat') }}
                </span>
            </div>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Last 3-Month Trend</p>
                    <p class="mt-3 text-3xl font-bold {{ $changeClass($summary['last_3m_change']) }}">{{ $formatChange($summary['last_3m_change']) }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ number_format($summary['last_3m_total']) }} vs {{ number_format($summary['prev_3m_total']) }}</p>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass($summary['last_3m_change']) }}">
                    Recent
                </span>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">What&apos;s Driving Change</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">What&apos;s driving change</h2>
            <p class="mt-3 text-sm text-zinc-700">
                Crime is {{ $summary['pct_change'] >= 0 ? 'up' : 'down' }} {{ number_format(abs($drivers['overall_yoy']), 1) }}% in this area.
            </p>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Driven by</p>
                    <div class="mt-3 grid gap-2 text-sm text-zinc-700">
                        @forelse ($drivers['increases'] as $increase)
                            <div class="flex items-start gap-2">
                                <span class="mt-0.5 text-red-600">•</span>
                                <span>{{ $increase['type'] }} ↑ {{ number_format($increase['yoy_change'], 1) }}%</span>
                            </div>
                        @empty
                            <div class="text-zinc-600">No material category increases.</div>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Offset by</p>
                    <div class="mt-3 grid gap-2 text-sm text-zinc-700">
                        @forelse ($drivers['decreases'] as $decrease)
                            <div class="flex items-start gap-2">
                                <span class="mt-0.5 text-emerald-600">•</span>
                                <span>{{ $decrease['type'] }} ↓ {{ number_format(abs($decrease['yoy_change']), 1) }}%</span>
                            </div>
                        @empty
                            <div class="text-zinc-600">No material category declines.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Crime Breakdown</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Share of total crime (%)</h2>
            <div class="mt-6 h-80">
                <canvas id="crime-type-chart" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Monthly Trend</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">{{ $area }} monthly crime</h2>
                    <p class="mt-2 text-sm text-zinc-600">Monthly crime compared to the same period last year</p>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Latest 12 months vs previous 12 months</span>
            </div>
            <div class="mt-6 h-72">
                <canvas id="crime-area-chart" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Crime Breakdown</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Latest 12 months by type</h2>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-medium">
                        <a href="{{ $breakdownSortLinks['impact_desc'] }}" class="rounded-full border px-3 py-1 {{ $breakdown_sort === 'impact_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Impact desc</a>
                        <a href="{{ $breakdownSortLinks['total_desc'] }}" class="rounded-full border px-3 py-1 {{ $breakdown_sort === 'total_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Total desc</a>
                        <a href="{{ $breakdownSortLinks['yoy_desc'] }}" class="rounded-full border px-3 py-1 {{ $breakdown_sort === 'yoy_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">YoY desc</a>
                        <a href="{{ $breakdownSortLinks['share_desc'] }}" class="rounded-full border px-3 py-1 {{ $breakdown_sort === 'share_desc' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700' }}">Share desc</a>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Crime type</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Total 12m</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">YoY change</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Share %</th>
                            <th class="px-6 py-3 text-left font-semibold uppercase tracking-[0.2em] text-zinc-500">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @forelse ($crime_breakdown as $crimeType)
                            <tr>
                                <td class="px-6 py-4 font-medium text-zinc-900">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span>{{ $crimeType['type'] }}</span>
                                        @if ($crimeType['is_largest'])
                                            <span class="rounded-full border border-lime-200 bg-lime-50 px-2 py-0.5 text-[11px] font-semibold text-lime-700">
                                                Largest category · {{ number_format($crimeType['share_pct'], 1) }}%
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-zinc-700">{{ number_format($crimeType['total_12m']) }}</td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold {{ $changeClass($crimeType['yoy_change']) }}">{{ $formatChange($crimeType['yoy_change']) }}</div>
                                    @if ($crimeType['national_yoy'] !== null)
                                        <div class="mt-1 text-xs text-zinc-500">vs {{ $formatChange($crimeType['national_yoy']) }} nationally</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2.5 w-28 overflow-hidden rounded-full bg-zinc-100">
                                            <div class="h-full rounded-full bg-sky-500" style="width: {{ max(0, min(100, $crimeType['share_pct'])) }}%"></div>
                                        </div>
                                        <span class="text-zinc-700">{{ number_format($crimeType['share_pct'], 1) }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass($crimeType['yoy_change']) }}">
                                        <span>{{ $crimeType['trend'] === 'Up' ? '↑' : ($crimeType['trend'] === 'Down' ? '↓' : '→') }}</span>
                                        <span>{{ $crimeType['trend'] }}</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-zinc-600">No crime type breakdown is available for this area yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</div>

<script>
    const areaChartData = {{ \Illuminate\Support\Js::from($chart) }};
    const crimeBreakdown = {{ \Illuminate\Support\Js::from($crime_breakdown) }};
    const sortedTypeBreakdown = [...crimeBreakdown].sort((left, right) => right.share_pct - left.share_pct);

    const areaCanvas = document.getElementById('crime-area-chart');

    if (areaCanvas) {
        new Chart(areaCanvas, {
            type: 'line',
            data: {
                labels: areaChartData.labels,
                datasets: [
                    {
                        label: 'Latest 12 months',
                        data: areaChartData.current_year,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        fill: true,
                        borderWidth: 3,
                        tension: 0.28,
                    },
                    {
                        label: 'Previous 12 months',
                        data: areaChartData.previous_year,
                        borderColor: '#94a3b8',
                        borderDash: [8, 6],
                        borderWidth: 2,
                        fill: false,
                        tension: 0.2,
                        pointRadius: 0,
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

    const typeCanvas = document.getElementById('crime-type-chart');

    if (typeCanvas) {
        new Chart(typeCanvas, {
            type: 'bar',
            data: {
                labels: sortedTypeBreakdown.map((item) => item.type),
                datasets: [{
                    label: 'Share of total crime (%)',
                    data: sortedTypeBreakdown.map((item) => item.share_pct),
                    backgroundColor: ['#2563eb', '#0f766e', '#84cc16', '#f97316', '#dc2626', '#7c3aed'],
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
                            label: (context) => {
                                const item = sortedTypeBreakdown[context.dataIndex];

                                return [
                                    `${Number(context.parsed.y).toFixed(1)}% share`,
                                    `${Number(item.total_12m).toLocaleString('en-GB')} crimes`,
                                ];
                            },
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: (value) => `${value}%`,
                        },
                    },
                },
            },
        });
    }
</script>
@endsection
