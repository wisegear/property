@extends('layouts.app')

@section('title', 'Housing Market Movement Dashboard')
@section('description', 'Quarter-on-quarter housing market movements across England and Wales using Land Registry transaction data.')

@section('content')
@php
    $momentumStyles = [
        'green' => 'border-green-200 bg-green-50 text-green-700',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
        'red' => 'border-red-200 bg-red-50 text-red-700',
    ];

    $currency = function (?int $value): string {
        return $value === null ? 'n/a' : '£'.number_format($value);
    };

    $metricCards = [
        [
            'title' => 'Transaction Volume Change',
            'value' => number_format($summary['sales_change_percent'], 1).'%',
            'badge' => $summary['sales_change_percent'],
            'sparkline' => $summary['transactions_sparkline'],
            'context' => number_format($summary['benchmark_transactions']).' vs '.number_format($summary['comparison_transactions']),
        ],
        [
            'title' => 'Median Price Change',
            'value' => number_format($summary['median_price_change_percent'], 1).'%',
            'badge' => $summary['median_price_change_percent'],
            'sparkline' => $summary['price_sparkline'],
            'context' => $currency($summary['benchmark_median_price']).' vs '.$currency($summary['comparison_median_price']),
        ],
    ];

    $formatChange = function (float $value): string {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.number_format($value, 1).'%';
    };

    $changeClass = function (float $value): string {
        if ($value > 0) {
            return 'text-green-600 bg-green-50 border-green-200';
        }

        if ($value < 0) {
            return 'text-red-600 bg-red-50 border-red-200';
        }

        return 'text-zinc-600 bg-zinc-50 border-zinc-200';
    };

    $tableHeadingClass = 'px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500 sm:px-4 sm:text-xs sm:tracking-[0.24em]';
    $tableCellClass = 'px-3 py-3 text-sm text-zinc-700 sm:px-4';
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="relative z-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-center">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Insights Dashboard
                </div>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl md:text-4xl">Housing Market Movement Dashboard</h1>
                <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-600">
                    This dashboard highlights the most significant movements in the England &amp; Wales housing market between two recent quarters using Land Registry transaction data. Counties are used to provide a localised view of market shifts, while national trends and property type movements offer broader context. The insights here are designed to help homeowners, buyers, and industry professionals understand where the market is moving most rapidly and which areas are showing resilience or weakness in the current environment.
                </p>
                <div class="mt-5 flex flex-wrap gap-3 text-xs font-medium text-zinc-600">
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1">
                        Benchmark: {{ $benchmark_start->format('d M Y') }} to {{ $benchmark_end->format('d M Y') }}
                    </span>
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1">
                        Comparison: {{ $comparison_start->format('d M Y') }} to {{ $comparison_end->format('d M Y') }}
                    </span>
                </div>
                <div class="mt-5">
                    <a href="/insights" class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm transition hover:border-zinc-400 hover:bg-zinc-100">
                        View Granular Insights
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
            <div class="relative z-10 mt-2 flex justify-center lg:mt-0 lg:ml-8 lg:justify-end">
                <img src="{{ asset('/assets/images/site/property-insghts.jpg') }}" alt="Property market insights" class="h-auto w-full max-w-[15rem] sm:max-w-xs lg:max-w-sm">
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-5 md:grid-cols-2">
        @foreach ($metricCards as $index => $card)
            <article class="min-w-0 overflow-hidden flex h-full min-h-[13rem] flex-col rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">{{ $card['title'] }}</p>
                        <p class="mt-3 text-3xl font-bold text-zinc-900">{{ $card['value'] }}</p>
                        @isset($card['suffix'])
                            <p class="mt-2 text-sm text-zinc-500">{{ $card['suffix'] }}</p>
                        @endisset
                        @isset($card['context'])
                            <p class="mt-2 text-sm text-zinc-600">{{ $card['context'] }}</p>
                        @endisset
                    </div>
                    @isset($card['badge'])
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $changeClass($card['badge']) }}">
                            {{ $formatChange($card['badge']) }}
                        </span>
                    @endisset
                </div>
                <div class="mt-auto pt-3">
                    <div class="h-9 min-w-0 overflow-hidden">
                        <canvas id="metric-sparkline-{{ $index }}" class="block h-9 w-full max-w-full"></canvas>
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mt-6">
        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Market Momentum</p>
                    <p class="mt-3 text-3xl font-bold text-zinc-900">{{ $summary['market_momentum']['label'] }}</p>
                    <p class="mt-2 text-sm text-zinc-600">{{ $summary['market_momentum']['description'] }}</p>
                </div>
                <div class="flex flex-col gap-4 lg:items-end">
                    <span class="w-fit rounded-full border px-3 py-1 text-xs font-semibold {{ $momentumStyles[$summary['market_momentum']['tone']] ?? $momentumStyles['amber'] }}">
                        {{ ucfirst($summary['market_momentum']['tone']) }}
                    </span>
                    <div class="grid w-full grid-cols-3 gap-2 sm:max-w-56 lg:w-56">
                        <span class="h-2 rounded-full {{ $summary['market_momentum']['tone'] === 'green' ? 'bg-green-500' : 'bg-zinc-200' }}"></span>
                        <span class="h-2 rounded-full {{ $summary['market_momentum']['tone'] === 'amber' ? 'bg-amber-500' : 'bg-zinc-200' }}"></span>
                        <span class="h-2 rounded-full {{ $summary['market_momentum']['tone'] === 'red' ? 'bg-red-500' : 'bg-zinc-200' }}"></span>
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-2">
        <article class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">National Trend</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">Monthly transactions</h2>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Last 12 months</span>
            </div>
            <div class="mt-6 h-64 min-w-0 overflow-hidden sm:h-72 lg:h-80">
                <canvas id="monthly-transactions-chart" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>

        <article class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">National Trend</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">Median price trend</h2>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Last 12 months</span>
            </div>
            <div class="mt-6 h-64 min-w-0 overflow-hidden sm:h-72 lg:h-80">
                <canvas id="median-price-chart" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-6">
        <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Market Breadth</p>
            <p class="mt-2 text-sm text-zinc-600">How widespread the recent market movements are across counties.</p>

            <div class="mt-5 grid gap-6 md:grid-cols-2">

                <div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-zinc-700">Counties with falling transactions</p>
                        <span class="text-lg font-bold text-red-600">{{ $declining_counties }} / {{ $total_counties }}</span>
                    </div>

                    @php
                        $declinePercent = $total_counties > 0 ? ($declining_counties / $total_counties) * 100 : 0;
                    @endphp

                    <div class="mt-3 h-2 w-full rounded-full bg-zinc-200">
                        <div class="h-2 rounded-full bg-red-500" style="width: {{ $declinePercent }}%"></div>
                    </div>

                    <p class="mt-2 text-xs text-zinc-500">
                        {{ number_format($declinePercent,0) }}% of counties recorded lower transaction volumes.
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-zinc-700">Counties with rising prices</p>
                        <span class="text-lg font-bold text-emerald-600">{{ $rising_price_counties }} / {{ $total_counties }}</span>
                    </div>

                    @php
                        $priceRisePercent = $total_counties > 0 ? ($rising_price_counties / $total_counties) * 100 : 0;
                    @endphp

                    <div class="mt-3 h-2 w-full rounded-full bg-zinc-200">
                        <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $priceRisePercent }}%"></div>
                    </div>

                    <p class="mt-2 text-xs text-zinc-500">
                        {{ number_format($priceRisePercent,0) }}% of counties still recorded price increases.
                    </p>
                </div>

            </div>
        </article>
    </section>

    <section class="mt-8 grid items-stretch gap-6 xl:grid-cols-2">
        <article class="flex h-full flex-col rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <h2 class="text-xl font-semibold text-zinc-900">Top Transaction Growth Counties</h2>
            </div>
            <div class="flex-1 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="{{ $tableHeadingClass }}">County</th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Bench Txns</span><span class="hidden sm:inline">Benchmark Transactions</span></th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Comp Txns</span><span class="hidden sm:inline">Comparison Transactions</span></th>
                            <th class="{{ $tableHeadingClass }}">Change %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($countyMovers['top_sales_growth'] as $row)
                            <tr class="odd:bg-white even:bg-zinc-50">
                                <td class="{{ $tableCellClass }} font-semibold text-zinc-900">{{ $row['county'] }}</td>
                                <td class="{{ $tableCellClass }}">{{ number_format($row['benchmark_sales']) }}</td>
                                <td class="{{ $tableCellClass }}">{{ number_format($row['comparison_sales']) }}</td>
                                <td class="{{ $tableCellClass }} text-green-600">{{ $formatChange($row['sales_change_percent']) }}</td>
                            </tr>
                        @empty
                            <tr class="odd:bg-white even:bg-zinc-50"><td colspan="4" class="px-4 py-6 text-sm text-zinc-500">No transaction growth detected. Every county recorded lower transaction volumes in this comparison period, indicating a broad-based slowdown in market activity.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="flex h-full flex-col rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <h2 class="text-xl font-semibold text-zinc-900">Top Transaction Decline Counties</h2>
            </div>
            <div class="flex-1 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="{{ $tableHeadingClass }}">County</th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Bench Txns</span><span class="hidden sm:inline">Benchmark Transactions</span></th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Comp Txns</span><span class="hidden sm:inline">Comparison Transactions</span></th>
                            <th class="{{ $tableHeadingClass }}">Change %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($countyMovers['top_sales_decline'] as $row)
                            <tr class="odd:bg-white even:bg-zinc-50">
                                <td class="{{ $tableCellClass }} font-semibold text-zinc-900">{{ $row['county'] }}</td>
                                <td class="{{ $tableCellClass }}">{{ number_format($row['benchmark_sales']) }}</td>
                                <td class="{{ $tableCellClass }}">{{ number_format($row['comparison_sales']) }}</td>
                                <td class="{{ $tableCellClass }} text-red-600">{{ $formatChange($row['sales_change_percent']) }}</td>
                            </tr>
                        @empty
                            <tr class="odd:bg-white even:bg-zinc-50"><td colspan="4" class="px-4 py-6 text-sm text-zinc-500">No county-level transaction declines cleared the minimum threshold in both quarters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <section class="mt-6 grid items-stretch gap-6 xl:grid-cols-2">
        <article class="flex h-full flex-col rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <h2 class="text-xl font-semibold text-zinc-900">Top Price Growth Counties</h2>
            </div>
            <div class="flex-1 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="{{ $tableHeadingClass }}">County</th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Bench Price</span><span class="hidden sm:inline">Benchmark Price</span></th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Comp Price</span><span class="hidden sm:inline">Comparison Price</span></th>
                            <th class="{{ $tableHeadingClass }}">Change %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($countyMovers['top_price_growth'] as $row)
                            <tr class="odd:bg-white even:bg-zinc-50">
                                <td class="{{ $tableCellClass }} font-semibold text-zinc-900">{{ $row['county'] }}</td>
                                <td class="{{ $tableCellClass }}">{{ $currency($row['benchmark_median_price']) }}</td>
                                <td class="{{ $tableCellClass }}">{{ $currency($row['comparison_median_price']) }}</td>
                                <td class="{{ $tableCellClass }} text-green-600">{{ $formatChange($row['price_change_percent']) }}</td>
                            </tr>
                        @empty
                            <tr class="odd:bg-white even:bg-zinc-50"><td colspan="4" class="px-4 py-6 text-sm text-zinc-500">No county-level price increases cleared the minimum transaction threshold in both quarters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="flex h-full flex-col rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <h2 class="text-xl font-semibold text-zinc-900">Top Price Decline Counties</h2>
            </div>
            <div class="flex-1 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="{{ $tableHeadingClass }}">County</th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Bench Price</span><span class="hidden sm:inline">Benchmark Price</span></th>
                            <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Comp Price</span><span class="hidden sm:inline">Comparison Price</span></th>
                            <th class="{{ $tableHeadingClass }}">Change %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($countyMovers['top_price_decline'] as $row)
                            <tr class="odd:bg-white even:bg-zinc-50">
                                <td class="{{ $tableCellClass }} font-semibold text-zinc-900">{{ $row['county'] }}</td>
                                <td class="{{ $tableCellClass }}">{{ $currency($row['benchmark_median_price']) }}</td>
                                <td class="{{ $tableCellClass }}">{{ $currency($row['comparison_median_price']) }}</td>
                                <td class="{{ $tableCellClass }} text-red-600">{{ $formatChange($row['price_change_percent']) }}</td>
                            </tr>
                        @empty
                            <tr class="odd:bg-white even:bg-zinc-50"><td colspan="4" class="px-4 py-6 text-sm text-zinc-500">No county-level price declines cleared the minimum transaction threshold in both quarters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <section class="mt-8 grid items-stretch gap-6 xl:grid-cols-2">
        <article class="flex min-w-0 h-full flex-col rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 px-6 py-5">
                    <h2 class="text-xl font-semibold text-zinc-900">Top 10 emerging hotspots</h2>
                </div>
                <div class="flex-1 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200">
                        <thead class="bg-zinc-50">
                            <tr>
                                <th class="{{ $tableHeadingClass }}">County</th>
                                <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Txn Chg</span><span class="hidden sm:inline">Transaction Change</span></th>
                                <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Price Chg</span><span class="hidden sm:inline">Price Change</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($countyMovers['hotspots'] as $row)
                                <tr class="odd:bg-white even:bg-zinc-50">
                                    <td class="{{ $tableCellClass }} font-semibold text-zinc-900">{{ $row['county'] }}</td>
                                    <td class="{{ $tableCellClass }} text-green-600">{{ $formatChange($row['sales_change_percent']) }}</td>
                                    <td class="{{ $tableCellClass }} text-green-600">{{ $formatChange($row['price_change_percent']) }}</td>
                                </tr>
                            @empty
                                <tr class="odd:bg-white even:bg-zinc-50"><td colspan="3" class="px-4 py-6 text-sm text-zinc-500">No emerging hotspots detected. Transaction volumes fell across most counties during this period, limiting the emergence of strong localised growth.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </article>

        <article class="flex min-w-0 h-full flex-col rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-200 px-6 py-5">
                    <h2 class="text-xl font-semibold text-zinc-900">Top 10 cooling markets</h2>
                </div>
                <div class="flex-1 overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200">
                        <thead class="bg-zinc-50">
                            <tr>
                                <th class="{{ $tableHeadingClass }}">County</th>
                                <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Txn Chg</span><span class="hidden sm:inline">Transaction Change</span></th>
                                <th class="{{ $tableHeadingClass }}"><span class="sm:hidden">Price Chg</span><span class="hidden sm:inline">Price Change</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($countyMovers['cooling_markets'] as $row)
                                <tr class="odd:bg-white even:bg-zinc-50">
                                    <td class="{{ $tableCellClass }} font-semibold text-zinc-900">{{ $row['county'] }}</td>
                                    <td class="{{ $tableCellClass }} text-red-600">{{ $formatChange($row['sales_change_percent']) }}</td>
                                    <td class="{{ $tableCellClass }} {{ $row['price_change_percent'] < 0 ? 'text-red-600' : 'text-zinc-700' }}">{{ $formatChange($row['price_change_percent']) }}</td>
                                </tr>
                            @empty
                                <tr class="odd:bg-white even:bg-zinc-50"><td colspan="3" class="px-4 py-6 text-sm text-zinc-500">No cooling markets match the threshold right now.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </article>
    </section>

    <section class="mt-6">
        <article class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Property Type Shifts</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">Transaction change by property type</h2>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Quarter comparison</span>
            </div>
            <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80 lg:h-96">
                <canvas id="property-type-chart" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>
    </section>
</div>
@endsection

@push('scripts')
<script>
    const dashboardChartData = {
        monthlyLabels: @json($monthlyTrends['labels']),
        monthlyTransactions: @json($monthlyTrends['transactions_values']),
        monthlyPrices: @json($monthlyTrends['price_values']),
        metricSparklines: @json(array_map(fn ($card) => $card['sparkline'], $metricCards)),
        propertyTypes: {
            labels: @json($propertyTypeMovements['labels']),
            benchmarkSales: @json($propertyTypeMovements['benchmark_sales']),
            comparisonSales: @json($propertyTypeMovements['comparison_sales']),
            changePercent: @json($propertyTypeMovements['change_percent']),
        },
    };

    const shortMonthLabels = dashboardChartData.monthlyLabels.map(label => {
        const d = new Date(label);
        if (!isNaN(d)) {
            const month = String(d.getMonth() + 1).padStart(2, '0');
            return `${month}-${d.getFullYear()}`;
        }
        return label;
    });

    const makeCurrencyTick = (value) => '£' + Number(value).toLocaleString('en-GB');

    const createSparkline = (id, values, color) => {
        const element = document.getElementById(id);

        if (!element) {
            return;
        }

        new Chart(element, {
            type: 'line',
            data: {
                labels: values.map((_, index) => index + 1),
                datasets: [{
                    data: values,
                    borderColor: color,
                    backgroundColor: color + '1A',
                    fill: true,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                },
                scales: {
                    x: { display: false },
                    y: { display: false },
                },
            },
        });
    };

    dashboardChartData.metricSparklines.forEach((series, index) => {
        const color = index === 1 ? '#2563eb' : '#65a30d';
        createSparkline('metric-sparkline-' + index, series, color);
    });

    new Chart(document.getElementById('monthly-transactions-chart'), {
        type: 'line',
        data: {
            labels: shortMonthLabels,
            datasets: [{
                label: 'Transactions',
                data: dashboardChartData.monthlyTransactions,
                borderColor: '#65a30d',
                backgroundColor: 'rgba(101, 163, 13, 0.14)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
            scales: {
                x: {
                    grid: { display: false },
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

    new Chart(document.getElementById('median-price-chart'), {
        type: 'line',
        data: {
            labels: shortMonthLabels,
            datasets: [{
                label: 'Median price',
                data: dashboardChartData.monthlyPrices,
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15, 118, 110, 0.12)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => makeCurrencyTick(context.parsed.y),
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                },
                y: {
                    ticks: {
                        callback: (value) => makeCurrencyTick(value),
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('property-type-chart'), {
        type: 'bar',
        data: {
            labels: dashboardChartData.propertyTypes.labels,
            datasets: [{
                label: 'Benchmark quarter',
                data: dashboardChartData.propertyTypes.benchmarkSales,
                backgroundColor: 'rgba(148, 163, 184, 0.75)',
                borderColor: '#64748b',
                borderWidth: 1,
            }, {
                label: 'Comparison quarter',
                data: dashboardChartData.propertyTypes.comparisonSales,
                backgroundColor: 'rgba(59, 130, 246, 0.72)',
                borderColor: '#2563eb',
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        afterBody: (items) => {
                            const index = items[0].dataIndex;
                            const change = dashboardChartData.propertyTypes.changePercent[index];
                            const prefix = change > 0 ? '+' : '';

                            return 'Change: ' + prefix + Number(change).toFixed(1) + '%';
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: (value) => Number(value).toLocaleString('en-GB')
                    }
                },
            },
        },
    });
</script>
@endpush
