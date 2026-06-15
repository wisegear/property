@extends('layouts.app')
@include('partials.chartjs-head')

@section('title', 'Scottish Prices')
@section('description', 'Yearly Scottish residential property prices and sales activity for Scotland or an individual local authority.')

@section('content')
@php
    $chartScopeLabel = $selectedAuthority ?? 'All Scotland';
@endphp
<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="relative z-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-4xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase text-zinc-600">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Scotland
                </div>
                @if ($latestCoveredMonth)
                    <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white/80 px-3 py-1 text-xs font-semibold uppercase text-zinc-600">
                        <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                        Latest data: {{ $latestCoveredMonth }}
                    </div>
                @endif
                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900 md:text-4xl">Scottish House Prices</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600 md:text-base">
                    Explore yearly Scottish residential property data across the whole of Scotland or focus on an individual local authority.
                    The charts below aggregate official monthly records into annual averages for prices and annual totals for sales activity.
                    There is limited data for Scotland but hopefully this will grow over time depending on the availability of official records.  Current
                    year is year to date.
                </p>
            </div>

            <div class="flex-shrink-0">
                <img src="{{ asset('assets/images/site/property1.jpg') }}" alt="Scottish property prices" class="h-auto w-90">
            </div>
        </div>
    </section>

    <section class="mx-auto mt-8 w-full rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6 lg:w-1/2">
        <form method="GET" action="{{ route('property.scottish-prices', absolute: false) }}" class="flex flex-col gap-4 lg:flex-row lg:items-end">
            <div class="flex-1">
                <label for="local_authority" class="block text-sm font-medium text-zinc-800">Local authority</label>
                <select
                    id="local_authority"
                    name="local_authority"
                    class="mt-2 w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-900 shadow-sm focus:border-lime-500 focus:outline-none focus:ring-2 focus:ring-lime-500/20"
                >
                    <option value="">All Scotland</option>
                    @foreach ($localAuthorities as $localAuthority)
                        <option value="{{ $localAuthority }}" @selected($selectedAuthority === $localAuthority)>{{ $localAuthority }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-lime-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-lime-700">
                    Update view
                </button>
                <a href="{{ route('property.scottish-prices', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl bg-zinc-200 px-5 py-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-300">
                    Reset
                </a>
            </div>
        </form>
    </section>

    @if ($years !== [])
        <section class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Latest Year to Date</p>
                <p class="mt-3 text-2xl font-semibold text-zinc-900">{{ $stats['latestYear'] }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Average</p>
                <p class="mt-3 text-2xl font-semibold text-zinc-900">£{{ number_format((float) $stats['latestMeanPrice']) }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Median</p>
                <p class="mt-3 text-2xl font-semibold text-zinc-900">£{{ number_format((float) $stats['latestMedianPrice']) }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Volume</p>
                <p class="mt-3 text-2xl font-semibold text-zinc-900">{{ number_format((int) $stats['latestSalesVolume']) }}</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Total Value</p>
                <p class="mt-3 text-2xl font-semibold text-zinc-900">£{{ number_format((float) $stats['latestSalesValue']) }}</p>
            </article>
        </section>

        <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Price Trend · {{ $chartScopeLabel }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Average residential property price by year</h2>
                        <p class="mt-2 text-sm text-zinc-600">Yearly average of mean residential property prices.</p>
                    </div>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="mean-price-chart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Price Trend · {{ $chartScopeLabel }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Median residential property price by year</h2>
                        <p class="mt-2 text-sm text-zinc-600">Yearly average of median residential property prices.</p>
                    </div>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="median-price-chart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Sales Activity · {{ $chartScopeLabel }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Volume of residential property sales by year</h2>
                        <p class="mt-2 text-sm text-zinc-600">Total residential property sales recorded each year.</p>
                    </div>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="sales-volume-chart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Sales Activity · {{ $chartScopeLabel }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Value of property sales by year</h2>
                        <p class="mt-2 text-sm text-zinc-600">Total value of residential property sales recorded each year.</p>
                    </div>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="sales-value-chart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>
        </section>
    @else
        <section class="mt-8 rounded-2xl border border-dashed border-zinc-300 bg-white p-8 text-center shadow-sm">
            <h2 class="text-xl font-semibold text-zinc-900">No data available</h2>
            <p class="mt-3 text-sm text-zinc-600">
                No yearly Scottish property price records were found for {{ $selectedAuthority ?? 'this selection' }}.
                Try switching back to all Scotland or choose a different local authority.
            </p>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const years = @json($years);
    const meanPrices = @json($meanPrices);
    const medianPrices = @json($medianPrices);
    const salesVolumes = @json($salesVolumes);
    const salesValues = @json($salesValues);

    if (!years.length || typeof Chart === 'undefined') {
        return;
    }

    const gbpFormatter = new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        maximumFractionDigits: 0,
    });

    const compactGbpFormatter = new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
        notation: 'compact',
        maximumFractionDigits: 1,
    });

    const integerFormatter = new Intl.NumberFormat('en-GB');

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                backgroundColor: 'rgba(24, 24, 27, 0.94)',
                titleColor: '#fafafa',
                bodyColor: '#f4f4f5',
                borderColor: 'rgba(161, 161, 170, 0.35)',
                borderWidth: 1,
                padding: 12,
            },
        },
        scales: {
            x: {
                grid: {
                    display: false,
                },
                ticks: {
                    color: '#52525b',
                    maxRotation: 0,
                    autoSkip: true,
                },
                border: {
                    color: 'rgba(113, 113, 122, 0.22)',
                },
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(113, 113, 122, 0.12)',
                },
                ticks: {
                    color: '#52525b',
                },
                border: {
                    color: 'rgba(113, 113, 122, 0.22)',
                },
            },
        },
    };

    new Chart(document.getElementById('mean-price-chart'), {
        type: 'line',
        data: {
            labels: years,
            datasets: [{
                data: meanPrices,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.16)',
                fill: true,
                tension: 0.28,
                pointRadius: 2.5,
                pointHoverRadius: 4,
            }],
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: (context) => gbpFormatter.format(context.parsed.y ?? 0),
                    },
                },
            },
            scales: {
                ...commonOptions.scales,
                y: {
                    ...commonOptions.scales.y,
                    ticks: {
                        ...commonOptions.scales.y.ticks,
                        callback: (value) => compactGbpFormatter.format(value),
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('median-price-chart'), {
        type: 'line',
        data: {
            labels: years,
            datasets: [{
                data: medianPrices,
                borderColor: '#0891b2',
                backgroundColor: 'rgba(8, 145, 178, 0.14)',
                fill: true,
                tension: 0.28,
                pointRadius: 2.5,
                pointHoverRadius: 4,
            }],
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: (context) => gbpFormatter.format(context.parsed.y ?? 0),
                    },
                },
            },
            scales: {
                ...commonOptions.scales,
                y: {
                    ...commonOptions.scales.y,
                    ticks: {
                        ...commonOptions.scales.y.ticks,
                        callback: (value) => compactGbpFormatter.format(value),
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('sales-volume-chart'), {
        type: 'bar',
        data: {
            labels: years,
            datasets: [{
                data: salesVolumes,
                backgroundColor: 'rgba(101, 163, 13, 0.78)',
                borderColor: '#4d7c0f',
                borderRadius: 10,
                maxBarThickness: 34,
            }],
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: (context) => integerFormatter.format(context.parsed.y ?? 0) + ' sales',
                    },
                },
            },
            scales: {
                ...commonOptions.scales,
                y: {
                    ...commonOptions.scales.y,
                    ticks: {
                        ...commonOptions.scales.y.ticks,
                        callback: (value) => integerFormatter.format(value),
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('sales-value-chart'), {
        type: 'bar',
        data: {
            labels: years,
            datasets: [{
                data: salesValues,
                backgroundColor: 'rgba(217, 119, 6, 0.78)',
                borderColor: '#b45309',
                borderRadius: 10,
                maxBarThickness: 34,
            }],
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: {
                    ...commonOptions.plugins.tooltip,
                    callbacks: {
                        label: (context) => gbpFormatter.format(context.parsed.y ?? 0),
                    },
                },
            },
            scales: {
                ...commonOptions.scales,
                y: {
                    ...commonOptions.scales.y,
                    ticks: {
                        ...commonOptions.scales.y.ticks,
                        callback: (value) => compactGbpFormatter.format(value),
                    },
                },
            },
        },
    });
})();
</script>
@endpush
