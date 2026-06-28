@extends('layouts.app')
@include('partials.chartjs-head')

@section('title', 'UK Swap Rates Today | 2, 5 and 10 Year SONIA Swap Rates')
@section('description', 'Track UK 2-year, 5-year and 10-year SONIA swap rates and see what recent movements may mean for fixed mortgage pricing.')

@section('content')
@php
    $cardMeta = [
        2 => '2Y Swap',
        5 => '5Y Swap',
        10 => '10Y Swap',
    ];

    $chartPalette = [
        2 => ['line' => '#2563eb', 'fill' => 'rgba(37, 99, 235, 0.12)'],
        5 => ['line' => '#0f766e', 'fill' => 'rgba(15, 118, 110, 0.12)'],
        10 => ['line' => '#a16207', 'fill' => 'rgba(161, 98, 7, 0.12)'],
    ];

    $formatRate = function ($value): string {
        return $value === null ? '—' : number_format((float) $value, 2).'%';
    };

    $formatMovement = function ($value): string {
        if ($value === null) {
            return 'Latest movement unavailable';
        }

        $prefix = (float) $value > 0 ? '+' : '';

        return $prefix.number_format((float) $value, 2).' pts';
    };

    $formatBasisPoints = function ($value): string {
        if ($value === null) {
            return '—';
        }

        $basisPoints = (float) $value * 100;
        $prefix = $basisPoints > 0 ? '+' : '';

        return $prefix.number_format($basisPoints, 1).' bps';
    };

    $basisPointClass = function ($value): string {
        if ($value === null) {
            return 'text-zinc-500';
        }

        if ((float) $value > 0) {
            return 'text-rose-700';
        }

        if ((float) $value < 0) {
            return 'text-emerald-700';
        }

        return 'text-zinc-700';
    };

    $formatCardDate = function ($value): string {
        if (! $value instanceof \Carbon\CarbonInterface) {
            return 'Latest available data';
        }

        return 'As at: '.$value->format('d M Y');
    };

    $formatChartDates = function (array $labels): array {
        return array_map(
            fn (string $label): string => \Illuminate\Support\Carbon::parse($label)->format('d M Y'),
            $labels
        );
    };

    $formatRange = function (?array $range) use ($formatRate): string {
        $low = $range['low'] ?? null;
        $high = $range['high'] ?? null;

        if ($low === null || $high === null) {
            return 'No data available';
        }

        return $formatRate($low).' - '.$formatRate($high);
    };

    $bankRatePalette = [
        'Bank Rate' => ['line' => '#18181b', 'fill' => 'rgba(24, 24, 27, 0.08)'],
        '2Y Swap' => ['line' => '#2563eb', 'fill' => 'rgba(37, 99, 235, 0.12)'],
        '5Y Swap' => ['line' => '#0f766e', 'fill' => 'rgba(15, 118, 110, 0.12)'],
    ];

    $signalClass = function (?array $summary): string {
        return match ($summary['signal_direction'] ?? null) {
            'improving' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'worsening' => 'border-rose-200 bg-rose-50 text-rose-900',
            default => 'border-zinc-200 bg-zinc-50 text-zinc-900',
        };
    };

    $trendClass = function (?array $trend): string {
        return match ($trend['direction'] ?? null) {
            'falling' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'rising' => 'border-rose-200 bg-rose-50 text-rose-800',
            default => 'border-zinc-200 bg-zinc-50 text-zinc-700',
        };
    };

    $sparklinePayload = collect($termSnapshots ?? [])
        ->mapWithKeys(function (array $snapshot) use ($chartPalette): array {
            return [
                $snapshot['term_years'] => [
                    'label' => $snapshot['label'],
                    'data' => $snapshot['sparkline'],
                    'labels' => $snapshot['sparkline_dates'],
                    'borderColor' => $chartPalette[$snapshot['term_years']]['line'] ?? '#3f3f46',
                    'backgroundColor' => $chartPalette[$snapshot['term_years']]['fill'] ?? 'rgba(63, 63, 70, 0.12)',
                ],
            ];
        })
        ->all();
@endphp

<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <section class="relative z-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-center">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Insights
                </div>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl md:text-4xl">UK Swap Rates Today</h1>
                <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-600">
                    Track the latest UK 2-year, 5-year and 10-year SONIA swap rates and see what recent wholesale moves may mean for fixed mortgage pricing.
                </p>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600">
                    Swap rates are one of the main market inputs lenders watch when pricing fixed mortgages. This page focuses on the latest available Bank of England data, recent momentum and the wider mortgage context without adding noise.
                </p>
                <p class="mt-3 max-w-3xl text-xs leading-6 text-zinc-500 sm:text-sm">
                    Data uses the Bank of England Overnight Index Swap curve, based on SONIA. Longer-term OIS data, including 10 year rates, is only available from late 2021 onwards.
                </p>
                <div class="mt-5 flex flex-wrap gap-3 text-xs font-medium text-zinc-600">
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1">
                        Latest available date:
                        {{ $latestAvailableDate instanceof \Carbon\CarbonInterface ? $latestAvailableDate->format('d M Y') : 'No data available' }}
                    </span>
                </div>
            </div>
            <div class="relative z-10 mt-2 flex justify-center lg:mt-0 lg:justify-end">
                <div class="w-90 overflow-hidden rounded-2xl">
                    <img
                        src="{{ asset('assets/images/site/swap-rates.jpg') }}"
                        alt="UK swap rates illustration"
                        class="h-auto w-full"
                    >
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8">
        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Mortgage Market Summary</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $signalClass($mortgageMarketSummary) }}">
                            Overall signal:
                            {{ $mortgageMarketSummary['signal'] ?? 'Unavailable' }}
                        </span>
                        @if ($latestMovementSummary)
                            <span class="text-sm text-zinc-500">{{ $latestMovementSummary['text'] }}</span>
                        @endif
                    </div>
                    <p class="mt-4 text-sm leading-7 text-zinc-600">
                        {{ $mortgageMarketSummary['explanation'] ?? 'Latest swap-rate summary is not available yet.' }}
                    </p>
                </div>
                <div class="text-sm text-zinc-500">
                    {{ $latestAvailableDate instanceof \Carbon\CarbonInterface ? 'Latest available data: '.$latestAvailableDate->format('d M Y') : 'Latest available data pending' }}
                </div>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach ($cardMeta as $term => $title)
                    @php
                        $snapshot = $termSnapshots[$term] ?? null;
                        $range = $rateRanges[$term] ?? null;
                    @endphp
                    <article class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">{{ $title }}</p>
                                <p class="mt-3 text-3xl font-bold text-zinc-900">{{ $formatRate($snapshot['latest_rate'] ?? null) }}</p>
                            </div>
                            @if (($snapshot['trend'] ?? null) !== null)
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-medium {{ $trendClass($snapshot['trend']) }}">
                                    {{ $snapshot['trend']['label'] }}
                                </span>
                            @endif
                        </div>

                        @if (($snapshot['latest_movement'] ?? null) !== null)
                            <p class="mt-3 text-sm font-medium {{ $basisPointClass($snapshot['latest_movement']) }}">
                                {{ $formatBasisPoints($snapshot['latest_movement']) }} on latest move
                            </p>
                        @endif

                        <p class="mt-3 text-sm text-zinc-500">{{ $formatCardDate($snapshot['latest_rate_date'] ?? null) }}</p>

                        @if (count($snapshot['sparkline'] ?? []) >= 2)
                            <div class="mt-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Last 30 available points</p>
                                <div class="mt-2 h-16">
                                    <canvas
                                        data-swap-sparkline="{{ $term }}"
                                        aria-label="{{ $title }} last 30 available data points"
                                        role="img"
                                        class="block h-full w-full"
                                    ></canvas>
                                </div>
                            </div>
                        @endif

                        <div class="mt-4 border-t border-zinc-200 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">52 week range</p>
                            <p class="mt-2 text-sm text-zinc-700">{{ $formatRange($range) }}</p>
                            @if (($snapshot['five_day_change'] ?? null) !== null)
                                <p class="mt-2 text-sm text-zinc-500">
                                    5-day change:
                                    <span class="{{ $basisPointClass($snapshot['five_day_change']) }}">{{ $formatMovement($snapshot['five_day_change']) }}</span>
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </article>
    </section>

    @if ($latestMovementDetails)
        <section class="mt-6">
            <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">{{ $latestMovementDetails['label'] }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">{{ $latestMovementDetails['title'] }}</h2>
                    </div>
                    <p class="text-sm text-zinc-500">
                        {{ $latestAvailableDate instanceof \Carbon\CarbonInterface ? $latestAvailableDate->format('d M Y') : 'No data available' }}
                    </p>
                </div>
                <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div class="space-y-3 text-sm leading-7 text-zinc-600">
                        @foreach ($latestMovementDetails['lines'] as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                        <p class="font-medium text-zinc-800">{{ $latestMovementDetails['interpretation'] }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Biggest mover</p>
                        <p class="mt-2 text-lg font-semibold text-zinc-900">{{ $latestMovementDetails['biggest_mover'] }}</p>
                    </div>
                </div>
            </article>
        </section>
    @endif

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Mortgage Context</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">What this means for mortgages</h2>
            <div class="mt-4 space-y-4 text-sm leading-7 text-zinc-600">
                <p>Swap rates are wholesale market rates lenders watch when pricing fixed mortgages.</p>
                <p>When swap rates fall, pressure on fixed mortgage pricing can ease, which may give some lenders room to trim rates.</p>
                <p>When swap rates rise, mortgage price cuts can become less likely and some lenders may face more pressure to reprice higher.</p>
                <p>Mortgage rates do not move perfectly with swaps. Lenders also consider margins, funding costs, competition, service levels and risk appetite before changing deals.</p>
            </div>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Understanding Swaps</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Why swap rates matter before Bank Rate moves</h2>
            <div class="mt-4 space-y-4 text-sm leading-7 text-zinc-600">
                <p>The Bank of England Bank Rate has the clearest direct effect on tracker mortgages and standard variable rates.</p>
                <p>Fixed mortgage pricing reacts more quickly to changes in market expectations, and swap rates are one of the clearest signals of that shift.</p>
                <p>That is why fixed deals can move even when Bank Rate is unchanged. Markets can price in future rate expectations before the next MPC decision arrives.</p>
            </div>
        </article>
    </section>

    <section class="mt-8">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Main Chart</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">UK swap rates over time</h2>
                    <p class="mt-2 text-sm text-zinc-600">2Y, 5Y and 10Y SONIA/OIS swap rates from the Bank of England.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach (['1Y', '5Y', '10Y', 'All'] as $range)
                        <button
                            type="button"
                            class="swap-range-button rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs font-semibold text-zinc-600 transition hover:border-lime-300 hover:text-lime-700"
                            data-range="{{ $range }}"
                            @if ($range === '5Y') data-active="true" @endif
                        >
                            {{ $range }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="mt-6 h-[26rem] min-w-0 overflow-hidden sm:h-[30rem]">
                <canvas id="swap-rates-chart" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>
    </section>

    <section class="mt-6">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Comparison</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">Bank Rate vs swap rates</h2>
                    <p class="mt-2 text-sm text-zinc-600">This shows why fixed mortgage pricing can move before the Bank of England changes Base Rate.</p>
                </div>
                <p class="text-sm text-zinc-500">Percent per annum</p>
            </div>
            @if ($bankRateComparisonChart !== null)
                <div class="mt-6 h-80 min-w-0 overflow-hidden sm:h-96">
                    <canvas id="bank-rate-vs-swaps-chart" class="block h-full w-full max-w-full"></canvas>
                </div>
            @else
                <div class="mt-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-sm text-zinc-600">
                    Bank Rate comparison data is not available yet.
                </div>
            @endif
        </article>
    </section>

    <section class="mt-6">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Current Rates</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Current UK Swap Rates</h2>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-zinc-700">Term</th>
                            <th class="px-4 py-3 font-semibold text-zinc-700">Rate</th>
                            <th class="px-4 py-3 font-semibold text-zinc-700">Latest movement</th>
                            <th class="px-4 py-3 font-semibold text-zinc-700">5-day change</th>
                            <th class="px-4 py-3 font-semibold text-zinc-700">As at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($currentRatesTable as $row)
                            <tr>
                                <td class="px-4 py-3 text-zinc-900">{{ $row['term'] }}</td>
                                <td class="px-4 py-3 text-zinc-900">{{ $formatRate($row['rate']) }}</td>
                                <td class="px-4 py-3 font-medium {{ $basisPointClass($row['daily_change']) }}">{{ $formatBasisPoints($row['daily_change']) }}</td>
                                <td class="px-4 py-3 font-medium {{ $basisPointClass($row['five_day_change']) }}">{{ $formatBasisPoints($row['five_day_change']) }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $row['rate_date'] instanceof \Carbon\CarbonInterface ? $row['rate_date']->format('d M Y') : 'Latest available data' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-sm text-zinc-500">Updated on UK business days when new Bank of England data is available. Weekends and market gaps use the latest available records.</p>
        </article>
    </section>

    <section class="mt-6">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">FAQ</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Swap rate questions</h2>
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    <h3 class="text-base font-semibold text-zinc-900">What are swap rates?</h3>
                    <p class="mt-2 text-sm leading-7 text-zinc-600">Swap rates are wholesale market interest rates that help lenders judge the cost of offering fixed-rate lending over different time periods.</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    <h3 class="text-base font-semibold text-zinc-900">Why do swap rates matter for mortgages?</h3>
                    <p class="mt-2 text-sm leading-7 text-zinc-600">They are one of the main market inputs behind fixed mortgage pricing, so sustained moves in swaps can influence whether lenders cut, hold or raise deals.</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    <h3 class="text-base font-semibold text-zinc-900">Do mortgage rates change immediately when swap rates move?</h3>
                    <p class="mt-2 text-sm leading-7 text-zinc-600">Not always. Lenders also consider margins, competition, funding costs and risk before changing mortgage pricing.</p>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    <h3 class="text-base font-semibold text-zinc-900">What is the difference between Bank Rate and swap rates?</h3>
                    <p class="mt-2 text-sm leading-7 text-zinc-600">Bank Rate is the official rate set by the Bank of England. Swap rates reflect market expectations for future rates and tend to matter more for fixed mortgages.</p>
                </div>
            </div>
        </article>
    </section>

    <section class="mt-6">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Explore The Wider Market</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Explore the wider market</h2>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-zinc-600">
                Swap rates are one part of the mortgage picture. You can also explore Bank Rate, mortgage approvals, house prices and local property data across PropertyResearch.
            </p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('mortgagecalc.index') }}" class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-800 transition hover:border-lime-300 hover:text-lime-700">Mortgage calculator</a>
                <a href="{{ route('interest.home') }}" class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-800 transition hover:border-lime-300 hover:text-lime-700">Interest rates and Bank Rate</a>
                <a href="{{ route('mortgages.home') }}" class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-800 transition hover:border-lime-300 hover:text-lime-700">Mortgage approvals</a>
                <a href="{{ route('hpi.home') }}" class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-800 transition hover:border-lime-300 hover:text-lime-700">House Price Index</a>
                <a href="{{ route('economic.dashboard') }}" class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-800 transition hover:border-lime-300 hover:text-lime-700">Market Stress Dashboard</a>
                <a href="{{ route('property.search') }}" class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-800 transition hover:border-lime-300 hover:text-lime-700">Property and postcode search</a>
            </div>
        </article>
    </section>
</div>

@php
    $rateChartPayload = [
        'labels' => $formatChartDates($rateChart['labels'] ?? []),
        'datasets' => collect($rateChart['datasets'] ?? [])->map(function (array $dataset) use ($chartPalette): array {
            $palette = $chartPalette[$dataset['term']] ?? ['line' => '#3f3f46', 'fill' => 'rgba(63, 63, 70, 0.12)'];

            return [
                'label' => $dataset['label'],
                'data' => $dataset['data'],
                'borderColor' => $palette['line'],
                'backgroundColor' => $palette['fill'],
            ];
        })->values()->all(),
    ];

    $bankRateComparisonPayload = $bankRateComparisonChart === null ? null : [
        'labels' => $formatChartDates($bankRateComparisonChart['labels'] ?? []),
        'datasets' => collect($bankRateComparisonChart['datasets'] ?? [])->map(function (array $dataset) use ($bankRatePalette): array {
            $palette = $bankRatePalette[$dataset['label']] ?? ['line' => '#3f3f46', 'fill' => 'rgba(63, 63, 70, 0.12)'];

            return [
                'label' => $dataset['label'],
                'data' => $dataset['data'],
                'borderColor' => $palette['line'],
                'backgroundColor' => $palette['fill'],
            ];
        })->values()->all(),
    ];
@endphp

<script>
(() => {
    const defaultOptions = {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                labels: {
                    usePointStyle: true,
                    boxWidth: 10,
                    color: '#3f3f46',
                },
            },
        },
        scales: {
            x: {
                ticks: {
                    color: '#71717a',
                    maxTicksLimit: 8,
                },
                grid: {
                    display: false,
                },
            },
            y: {
                ticks: {
                    color: '#71717a',
                },
                grid: {
                    color: 'rgba(161, 161, 170, 0.18)',
                },
            },
        },
    };

    const rateChartData = @json($rateChartPayload);
    const bankRateComparisonData = @json($bankRateComparisonPayload);
    const sparklineData = @json($sparklinePayload);

    const buildLineDatasets = (datasets, fill = false) => datasets.map((dataset) => ({
        label: dataset.label,
        data: dataset.data,
        borderColor: dataset.borderColor,
        backgroundColor: dataset.backgroundColor,
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 3,
        tension: 0.2,
        fill,
        spanGaps: true,
    }));

    const getRangeStartIndex = (labels, range) => {
        if (range === 'All') {
            return 0;
        }

        const years = {
            '1Y': 1,
            '5Y': 5,
            '10Y': 10,
        }[range];

        if (!years || labels.length === 0) {
            return 0;
        }

        const lastDate = new Date(labels[labels.length - 1]);
        const threshold = new Date(lastDate);
        threshold.setFullYear(threshold.getFullYear() - years);

        const index = labels.findIndex((label) => new Date(label) >= threshold);

        return index === -1 ? 0 : index;
    };

    const sliceChartDataByRange = (chartData, range) => {
        const startIndex = getRangeStartIndex(chartData.labels, range);

        return {
            labels: chartData.labels.slice(startIndex),
            datasets: chartData.datasets.map((dataset) => ({
                ...dataset,
                data: dataset.data.slice(startIndex),
            })),
        };
    };

    document.querySelectorAll('[data-swap-sparkline]').forEach((canvas) => {
        const term = canvas.dataset.swapSparkline;
        const dataset = sparklineData[term];

        if (!dataset || !Array.isArray(dataset.data) || dataset.data.length < 2) {
            return;
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: dataset.labels,
                datasets: [{
                    label: dataset.label,
                    data: dataset.data,
                    borderColor: dataset.borderColor,
                    backgroundColor: dataset.backgroundColor,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    tension: 0.25,
                    fill: false,
                    spanGaps: true,
                }],
            },
            options: {
                animation: false,
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: false,
                    },
                },
                scales: {
                    x: {
                        display: false,
                    },
                    y: {
                        display: false,
                    },
                },
                elements: {
                    line: {
                        capBezierPoints: true,
                    },
                },
            },
        });
    });

    const ratesCanvas = document.getElementById('swap-rates-chart');
    if (ratesCanvas) {
        const initialRateChartData = sliceChartDataByRange(rateChartData, '5Y');

        const rateChart = new Chart(ratesCanvas, {
            type: 'line',
            data: {
                labels: initialRateChartData.labels,
                datasets: buildLineDatasets(initialRateChartData.datasets),
            },
            options: {
                ...defaultOptions,
                plugins: {
                    ...defaultOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label(context) {
                                if (context.raw === null || context.raw === undefined) {
                                    return `${context.dataset.label}: no data`;
                                }

                                return `${context.dataset.label}: ${Number(context.raw).toFixed(2)}%`;
                            },
                        },
                    },
                },
            },
        });

        document.querySelectorAll('.swap-range-button').forEach((button) => {
            button.addEventListener('click', () => {
                const range = button.dataset.range || 'All';
                const filteredData = sliceChartDataByRange(rateChartData, range);

                rateChart.data.labels = filteredData.labels;
                rateChart.data.datasets = buildLineDatasets(filteredData.datasets);
                rateChart.update();

                document.querySelectorAll('.swap-range-button').forEach((otherButton) => {
                    otherButton.dataset.active = 'false';
                    otherButton.classList.remove('border-lime-400', 'bg-lime-50', 'text-lime-700');
                    otherButton.classList.add('border-zinc-200', 'bg-white', 'text-zinc-600');
                });

                button.dataset.active = 'true';
                button.classList.remove('border-zinc-200', 'bg-white', 'text-zinc-600');
                button.classList.add('border-lime-400', 'bg-lime-50', 'text-lime-700');
            });
        });

        document.querySelector('.swap-range-button[data-range="5Y"]')?.classList.add('border-lime-400', 'bg-lime-50', 'text-lime-700');
    }

    const bankRateCanvas = document.getElementById('bank-rate-vs-swaps-chart');
    if (bankRateCanvas && bankRateComparisonData) {
        new Chart(bankRateCanvas, {
            type: 'line',
            data: {
                labels: bankRateComparisonData.labels,
                datasets: buildLineDatasets(bankRateComparisonData.datasets),
            },
            options: {
                ...defaultOptions,
                plugins: {
                    ...defaultOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label(context) {
                                if (context.raw === null || context.raw === undefined) {
                                    return `${context.dataset.label}: no data`;
                                }

                                return `${context.dataset.label}: ${Number(context.raw).toFixed(2)}%`;
                            },
                        },
                    },
                },
            },
        });
    }
})();
</script>
@endsection
