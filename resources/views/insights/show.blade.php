@extends('layouts.app')

@section('title')
{{ $sector }} Property Market Trends, Prices & Sales Data | PropertyResearch.uk
@endsection

@section('meta_description')
Explore property price trends and sales activity in postcode sector {{ $sector }} using Land Registry data. Includes rolling 12-month price changes, transaction volumes, and historical market trends.
@endsection

@section('description')
Explore property price trends and sales activity in postcode sector {{ $sector }} using Land Registry data. Includes rolling 12-month price changes, transaction volumes, and historical market trends.
@endsection

@section('content')
@php
    $insightBadgeClasses = [
        'price_spike' => 'bg-amber-500 text-white border-amber-500',
        'price_collapse' => 'bg-stone-700 text-white border-stone-700',
        'demand_collapse' => 'bg-rose-500 text-white border-rose-500',
        'liquidity_stress' => 'bg-amber-600 text-white border-amber-600',
        'liquidity_surge' => 'bg-emerald-600 text-white border-emerald-600',
        'market_freeze' => 'bg-slate-700 text-white border-slate-700',
        'sector_outperformance' => 'bg-lime-600 text-white border-lime-600',
        'momentum_reversal' => 'bg-sky-600 text-white border-sky-600',
        'unexpected_hotspot' => 'bg-orange-500 text-white border-orange-500',
    ];
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="relative z-0 flex flex-col items-center justify-between overflow-hidden rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm lg:flex-row">
        @include('partials.hero-background')
        <div class="relative z-10 max-w-4xl">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Postcode Sector Insight Detail</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">Property Market Insights – {{ $sector }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600">
                Property price trends, transaction volumes and rolling 12-month market signals for postcode sector {{ $sector }} using Land Registry data.
            </p>
        </div>

        <div class="relative z-10 mt-6 flex-shrink-0 lg:ml-8 lg:mt-0">
            <img src="{{ asset('/assets/images/site/property-insghts.jpg') }}" alt="Property market insights" class="h-auto w-82">
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Current Insights</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Signals currently stored for {{ $sector }}</h2>
            </div>
            <a href="{{ route('insights.index') }}" class="text-sm font-medium text-lime-700 hover:text-lime-600">Back to insights</a>
        </div>

        <div class="mt-5 grid gap-4">
            @forelse ($insights as $insight)
                <article class="rounded-xl border border-zinc-200 bg-zinc-50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="{{ $insightBadgeClasses[$insight->insight_type] ?? 'border-zinc-200 bg-zinc-100 text-zinc-800' }} inline-flex items-center rounded-full border px-3 py-1 text-xs tracking-wide">
                            {{ $insightTypes[$insight->insight_type] ?? str_replace('_', ' ', $insight->insight_type) }}
                        </span>
                        <div class="text-right text-sm text-zinc-600">
                            <p><span class="font-semibold text-zinc-900">{{ number_format((int) $insight->transactions) }}</span> transactions</p>
                            <p>{{ optional($insight->period_start)->format('d M Y') }} to {{ optional($insight->period_end)->format('d M Y') }}</p>
                        </div>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-zinc-700">{{ $insight->insight_text }}</p>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-6 text-sm text-zinc-600">
                    No current market insights are stored for {{ $sector }}.
                </div>
            @endforelse
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Insight Trigger</p>
        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Rolling 12-Month Price Change That Triggered Insight</h2>

        @if ($recentPriceChange)
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-zinc-200 bg-zinc-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Previous window median price</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ $recentPriceChange['previous_label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold text-zinc-900">£{{ number_format($recentPriceChange['previous_price']) }}</p>
                </article>

                <article class="rounded-xl border border-zinc-200 bg-zinc-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Current window median price</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ $recentPriceChange['current_label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold text-zinc-900">£{{ number_format($recentPriceChange['current_price']) }}</p>
                </article>

                <article class="rounded-xl border border-zinc-200 bg-zinc-50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Percentage change</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ $recentPriceChange['previous_label'] }} vs {{ $recentPriceChange['current_label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $recentPriceChange['growth'] >= 0 ? 'text-lime-700' : 'text-rose-600' }}">
                        {{ $recentPriceChange['growth'] >= 0 ? '+' : '' }}{{ number_format($recentPriceChange['growth'], 1) }}%
                    </p>
                </article>
            </div>
        @else
            <div class="mt-5 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-6 text-sm text-zinc-600">
                Not enough yearly median price history is available for {{ $sector }} to calculate a recent price comparison.
            </div>
        @endif

        <div class="mt-4 text-sm text-gray-500">
            Data based on Land Registry Category A transactions (standard sales), excluding new build properties.
            Postcode sectors must have at least {{ $minSectorTransactions }} transactions to generate an insight.
        </div>
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <article class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Rolling 12-Month Chart</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Rolling 12-Month Sales</h2>
            <p class="mt-1 text-sm text-gray-500">
                Each data point represents the previous 12 months of Land Registry transactions ending in that year.
                For example, the 2026 point represents Feb 2025 – Jan 2026.
            </p>
            <div class="mt-5 h-64 min-w-0 sm:h-72">
                <canvas id="rolling-sales-chart"></canvas>
            </div>
        </article>

        <article class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Rolling 12-Month Chart</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Rolling 12-Month Median Price</h2>
            <p class="mt-1 text-sm text-gray-500">
                Each data point represents the previous 12 months of Land Registry transactions ending in that year.
                For example, the 2026 point represents Feb 2025 – Jan 2026.
            </p>
            <div class="mt-5 h-64 min-w-0 sm:h-72">
                <canvas id="rolling-price-chart"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <article class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Historical Chart</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Sales per year</h2>
            <div class="mt-5 h-64 min-w-0 sm:h-72">
                <canvas id="sales-per-year-chart"></canvas>
            </div>
        </article>

        <article class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Historical Chart</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Median price per year</h2>
            <div class="mt-5 h-64 min-w-0 sm:h-72">
                <canvas id="median-price-per-year-chart"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Historical Data - Complete years only</p>
        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Land Registry yearly summary</h2>

        @if ($historyRows->isEmpty())
            <div class="mt-5 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-6 text-sm text-zinc-600">
                No Land Registry transaction history is available for {{ $sector }} yet.
            </div>
        @else
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left font-semibold text-zinc-700">Year</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold text-zinc-700">Sales</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold text-zinc-700">Median price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        @foreach ($historyRows as $row)
                            <tr>
                                <td class="px-4 py-3 text-zinc-900">{{ $row['year'] }}</td>
                                <td class="px-4 py-3 text-zinc-700">{{ number_format($row['sales']) }}</td>
                                <td class="px-4 py-3 text-zinc-700">
                                    {{ $row['median_price'] === null ? 'N/A' : '£'.number_format($row['median_price']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<script>
    const rollingSalesChartData = {{ \Illuminate\Support\Js::from($rollingSalesChart) }};
    const rollingPriceChartData = {{ \Illuminate\Support\Js::from($rollingPriceChart) }};
    const salesChartData = {{ \Illuminate\Support\Js::from($salesChart) }};
    const medianPriceChartData = {{ \Illuminate\Support\Js::from($medianPriceChart) }};

    const sharedChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
            padding: {
                left: 0,
                right: 8,
            },
        },
        plugins: {
            legend: {
                display: false,
            },
        },
        scales: {
            x: {
                grid: {
                    display: false,
                },
                ticks: {
                    autoSkip: true,
                    maxRotation: 0,
                    minRotation: 0,
                    padding: 8,
                },
            },
            y: {
                beginAtZero: true,
                ticks: {
                    maxTicksLimit: 6,
                },
            },
        },
    };

    new Chart(document.getElementById('rolling-sales-chart'), {
        type: 'line',
        data: {
            labels: rollingSalesChartData.labels,
            datasets: [{
                data: rollingSalesChartData.values,
                borderColor: '#84cc16',
                backgroundColor: 'rgba(163, 230, 53, 0.18)',
                borderWidth: 3,
                tension: 0.25,
                fill: true,
            }],
        },
        options: sharedChartOptions,
    });

    new Chart(document.getElementById('rolling-price-chart'), {
        type: 'line',
        data: {
            labels: rollingPriceChartData.labels,
            datasets: [{
                data: rollingPriceChartData.values,
                borderColor: '#0f766e',
                backgroundColor: 'rgba(13, 148, 136, 0.16)',
                borderWidth: 3,
                tension: 0.25,
                fill: true,
            }],
        },
        options: {
            ...sharedChartOptions,
            scales: {
                ...sharedChartOptions.scales,
                y: {
                    ticks: {
                        callback(value) {
                            return '£' + Number(value).toLocaleString();
                        },
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('sales-per-year-chart'), {
        type: 'line',
        data: {
            labels: salesChartData.labels,
            datasets: [{
                data: salesChartData.values,
                borderColor: '#65a30d',
                backgroundColor: 'rgba(132, 204, 22, 0.18)',
                borderWidth: 3,
                tension: 0.25,
                fill: true,
            }],
        },
        options: sharedChartOptions,
    });

    new Chart(document.getElementById('median-price-per-year-chart'), {
        type: 'line',
        data: {
            labels: medianPriceChartData.labels,
            datasets: [{
                data: medianPriceChartData.values,
                borderColor: '#0284c7',
                backgroundColor: 'rgba(14, 165, 233, 0.18)',
                borderWidth: 3,
                tension: 0.25,
                fill: true,
            }],
        },
        options: {
            ...sharedChartOptions,
            scales: {
                ...sharedChartOptions.scales,
                y: {
                    ticks: {
                        callback(value) {
                            return '£' + Number(value).toLocaleString();
                        },
                    },
                },
            },
        },
    });
</script>
@endsection
