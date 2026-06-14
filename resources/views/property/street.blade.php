@extends('layouts.app')
@include('partials.chartjs-head')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Street Sales</p>
        <h1 class="mt-2 text-2xl font-semibold text-zinc-900">{{ $streetName }}, {{ $outcode }} property sales</h1>
        <p class="mt-2 max-w-3xl text-sm text-zinc-600">
            Category A Land Registry sales for this street and postcode district, cached for 45 days.
        </p>

        @if($limitedData)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Sales data for this street is limited, so figures may be less reliable.
            </div>
        @endif
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Total sales</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format((int) ($summary['total_sales'] ?? 0)) }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Median sale price</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900">
                {{ $summary['median_sale_price'] !== null ? '£'.number_format((int) $summary['median_sale_price']) : 'N/A' }}
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Average sale price</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900">
                {{ $summary['average_sale_price'] !== null ? '£'.number_format((int) $summary['average_sale_price']) : 'N/A' }}
            </div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Latest sale date</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900">{{ $summary['latest_sale_date'] ?? 'N/A' }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">Highest sale</div>
            <div class="mt-2 text-2xl font-semibold text-zinc-900">
                {{ $summary['highest_sale'] !== null ? '£'.number_format((int) $summary['highest_sale']) : 'N/A' }}
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Chart</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-900">Yearly median price</h2>
            </div>
            <div class="mt-6 h-72">
                <canvas id="streetMedianPriceChart" class="h-full w-full"></canvas>
            </div>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Chart</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-900">Yearly sales count</h2>
            </div>
            <div class="mt-6 h-72">
                <canvas id="streetSalesCountChart" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    @if(! empty($crimeTrend))
        <section class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-lg">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h3 class="text-lg font-semibold text-zinc-600">Crime Trends near {{ $streetName }}, {{ $outcode }}</h3>

                @if($crimeDirection === 'rising')
                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
                        Rising
                    </span>
                @elseif($crimeDirection === 'falling')
                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">
                        Improving
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600">
                        Stable
                    </span>
                @endif
            </div>

            <p class="mb-2 text-base font-medium text-zinc-800">
                {{ $crimeSummary }}
            </p>

            <p class="mb-2 text-sm text-zinc-500">
                Compared to the previous 12 months, based on reported crimes within ~500m of this street centroid.
            </p>

            <p class="mb-1 text-xs text-zinc-500">
                Monthly Crime Volume (last 24 months, hover over to see detail)
            </p>

            <div class="mb-1 h-10">
                <canvas id="crimeSparkline" class="h-10 w-full"></canvas>
            </div>

            <div class="mb-4 mt-1 flex items-center justify-between gap-3 text-xs text-zinc-500">
                <span>Shows monthly crime levels over time. Peaks indicate higher crime periods.</span>
                <span>Latest: {{ collect($crimeTrendValues)->last() ?? 0 }} crimes</span>
            </div>

            <div class="grid grid-cols-1 gap-6 text-sm md:grid-cols-3">
                <div>
                    <div class="text-zinc-500">Overall Change</div>
                    <div class="text-2xl font-bold {{ $totalChange > 0 ? 'text-red-600' : ($totalChange < 0 ? 'text-green-600' : 'text-zinc-700') }}">
                        {{ $totalChange > 0 ? '+' : '' }}{{ $totalChange }}%
                    </div>
                    <div class="mt-2 text-xs">
                        @if($totalChange > 10)
                            <span class="text-red-600">Increasing trend</span>
                        @elseif($totalChange < -10)
                            <span class="text-green-600">Decreasing trend</span>
                        @else
                            <span class="text-zinc-500">Stable trend</span>
                        @endif
                    </div>
                </div>

                <div>
                    <div class="text-zinc-500">Rising Most</div>
                    <div class="font-semibold">{{ $topIncrease['crime_type'] ?? '-' }}</div>
                    <div class="text-sm text-zinc-600">{{ $topIncrease['pct_change_label'] ?? (($topIncrease['pct_change'] ?? 0).'%') }}</div>
                </div>

                <div>
                    <div class="text-zinc-500">Falling Most</div>
                    <div class="font-semibold">{{ $topDecrease['crime_type'] ?? '-' }}</div>
                    <div class="text-sm text-zinc-600">{{ $topDecrease['pct_change'] ?? 0 }}%</div>
                </div>
            </div>

            @if(! empty($crimeData))
                <div class="mt-6">
                    <details class="rounded-xl border border-zinc-200 bg-white px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-zinc-600 marker:text-zinc-500">
                            Crime Profile for This Street Area (Last 12 months)
                        </summary>

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left">
                                        <th class="py-2">Crime Type</th>
                                        <th class="py-2">Total</th>
                                        <th class="py-2">%</th>
                                        <th class="py-2">12m Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($crimeData as $crime)
                                        <tr class="border-b border-zinc-100">
                                            <td class="py-2">{{ $crime['crime_type'] }}</td>
                                            <td class="py-2">{{ $crime['total'] }}</td>
                                            <td class="py-2">{{ $crime['pct'] }}%</td>
                                            <td class="py-2">
                                                <span class="{{ ($crime['pct_change'] ?? 0) > 0 ? 'text-red-600' : (($crime['pct_change'] ?? 0) < 0 ? 'text-green-600' : 'text-zinc-500') }}">
                                                    {{ ($crime['pct_change'] ?? 0) > 0 ? '+' : '' }}{{ $crime['pct_change'] ?? 0 }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            @endif
        </section>
    @endif

</div>

@push('scripts')
<script>
    (function () {
        const yearlyMedianPrice = @json($yearlyMedianPrice);
        const yearlySalesCount = @json($yearlySalesCount);
        const crimeSparklineEl = document.getElementById('crimeSparkline');
        const crimeTrendColor = @json($totalChange > 0 ? '#ef4444' : ($totalChange < 0 ? '#16a34a' : '#3b82f6'));

        const currencyFormatter = new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            maximumFractionDigits: 0
        });

        const countFormatter = new Intl.NumberFormat('en-GB');

        const renderChart = function (canvasId, dataset, config) {
            const canvas = document.getElementById(canvasId);

            if (!canvas || !Array.isArray(dataset) || dataset.length === 0) {
                return;
            }

            new Chart(canvas.getContext('2d'), {
                type: config.type,
                data: {
                    labels: dataset.map(function (item) { return item.year; }),
                    datasets: [{
                        label: config.label,
                        data: dataset.map(function (item) { return item.value; }),
                        borderColor: config.borderColor,
                        backgroundColor: config.backgroundColor,
                        borderWidth: 2,
                        fill: config.fill,
                        tension: 0.25,
                        maxBarThickness: 36
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return config.tooltip(context.parsed.y ?? 0);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#52525b' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#52525b',
                                callback: function (value) {
                                    return config.axis(value);
                                }
                            }
                        }
                    }
                }
            });
        };

        if (crimeSparklineEl) {
            new Chart(crimeSparklineEl, {
                type: 'line',
                data: {
                    labels: @json($crimeTrendLabels ?? []),
                    datasets: [{
                        data: @json($crimeTrendValues ?? []),
                        borderColor: crimeTrendColor,
                        borderWidth: 1.5,
                        tension: 0.25,
                        pointRadius: function (context) {
                            const index = context.dataIndex;
                            const total = context.dataset.data.length;

                            return index === 0 || index === total - 1 ? 2 : 0;
                        },
                        pointBackgroundColor: crimeTrendColor,
                        fill: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: undefined,
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function (context) {
                                    return context.raw + ' crimes';
                                }
                            }
                        },
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false },
                    }
                }
            });
        }

        renderChart('streetMedianPriceChart', yearlyMedianPrice, {
            type: 'line',
            label: 'Median sale price',
            borderColor: '#65a30d',
            backgroundColor: 'rgba(101, 163, 13, 0.16)',
            fill: true,
            tooltip: function (value) { return currencyFormatter.format(value); },
            axis: function (value) { return '£' + countFormatter.format(value); }
        });

        renderChart('streetSalesCountChart', yearlySalesCount, {
            type: 'bar',
            label: 'Sales count',
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.72)',
            fill: false,
            tooltip: function (value) { return countFormatter.format(value) + ' sales'; },
            axis: function (value) { return countFormatter.format(value); }
        });
    })();
</script>
@endpush
@endsection
