@extends('layouts.app')
@include('partials.chartjs-head')

@section('title', 'UK Swap Rates')
@section('description', 'Daily SONIA/OIS swap rate data from the Bank of England, showing 2, 5 and 10 year market rates.')

@section('content')
@php
    $cardMeta = [
        2 => '2Y Swap',
        5 => '5Y Swap',
        10 => '10Y Swap',
    ];

    $chartPalette = [
        2 => ['line' => '#2563eb', 'fill' => 'rgba(37, 99, 235, 0.12)'],
        5 => ['line' => '#059669', 'fill' => 'rgba(5, 150, 105, 0.12)'],
        10 => ['line' => '#ca8a04', 'fill' => 'rgba(202, 138, 4, 0.14)'],
    ];

    $formatRate = function ($value): string {
        return $value === null ? '—' : number_format((float) $value, 2).'%';
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
            return 'text-rose-600';
        }

        if ((float) $value < 0) {
            return 'text-emerald-600';
        }

        return 'text-zinc-900';
    };

    $formatCardDate = function ($value): string {
        if (! $value instanceof \Carbon\CarbonInterface) {
            return 'No data available';
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

        return $formatRate($low).' – '.$formatRate($high);
    };

    $bankRatePalette = [
        'Bank Rate' => ['line' => '#18181b', 'fill' => 'rgba(24, 24, 27, 0.08)'],
        '2Y Swap' => ['line' => '#2563eb', 'fill' => 'rgba(37, 99, 235, 0.12)'],
        '5Y Swap' => ['line' => '#059669', 'fill' => 'rgba(5, 150, 105, 0.12)'],
    ];

    $movementPanelClasses = function (?array $summary): string {
        $direction = $summary['direction'] ?? null;

        return match ($direction) {
            'lower' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            'higher' => 'border-rose-200 bg-rose-50 text-rose-900',
            default => 'border-zinc-200 bg-white text-zinc-900',
        };
    };
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
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl md:text-4xl">UK Swap Rates</h1>
                <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-600">
                    Daily SONIA/OIS swap rate data from the Bank of England, showing 2, 5 and 10 year market rates.
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
                        class="w-full h-auto"
                    >
                </div>
            </div>
        </div>
    </section>

    <section class="mt-8 grid gap-5 md:grid-cols-3">
        @foreach ($cardMeta as $term => $title)
            @php
                /** @var \App\Models\SwapRate|null $latestRate */
                $latestRate = $latestRates[$term] ?? null;
                $rateRange = $rateRanges[$term] ?? null;
            @endphp
            <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">{{ $title }}</p>
                <p class="mt-3 text-3xl font-bold text-zinc-900">{{ $formatRate($latestRate?->rate) }}</p>
                <p class="mt-2 text-sm font-medium {{ $basisPointClass($latestRate?->daily_change) }}">
                    {{ $formatBasisPoints($latestRate?->daily_change) }}
                </p>
                <p class="mt-4 text-sm text-zinc-500">{{ $formatCardDate($latestRate?->rate_date) }}</p>
                <div class="mt-4 border-t border-zinc-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">52 week range</p>
                    <p class="mt-2 text-sm text-zinc-700">{{ $formatRange($rateRange) }}</p>
                </div>
            </article>
        @endforeach
    </section>

    @if ($latestMovementSummary)
        <section class="mt-5">
            <article class="rounded-xl border px-5 py-4 shadow-sm {{ $movementPanelClasses($latestMovementSummary) }}">
                <p class="text-sm font-medium">{{ $latestMovementSummary['text'] }}</p>
            </article>
        </section>
    @endif

    <section class="mt-8 grid gap-6 lg:grid-cols-2">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Explanation</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">What are swap rates?</h2>
            <div class="mt-4 space-y-4 text-sm leading-7 text-zinc-600">
                <p>Swap rates are market interest rates used by banks and lenders when pricing fixed-rate lending.</p>
                <p>A 2 year swap rate gives an indication of what the market thinks fixed-rate money costs over the next 2 years. A 5 year swap rate does the same over 5 years.</p>
                <p>Fixed mortgage rates are not set directly by swap rates, but swaps are one of the key ingredients lenders use when pricing fixed deals. When swap rates rise, fixed mortgage rates often become more expensive. When swap rates fall, lenders may have room to reduce fixed rates.</p>
                <p>The Bank of England Base Rate mainly affects tracker mortgages and standard variable rates. Swap rates are more closely linked to fixed-rate mortgage pricing because they reflect market expectations for future interest rates.</p>
            </div>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Mortgage Section</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Why do mortgage rates change before the Bank of England changes rates?</h2>
            <div class="mt-4 space-y-4 text-sm leading-7 text-zinc-600">
                <p>Mortgage lenders price fixed-rate deals using more than today&apos;s Bank of England Base Rate. They also look at wholesale funding costs and market expectations for where interest rates may go next.</p>
                <p>That is why fixed mortgage rates can rise or fall even when the Bank of England has not changed Base Rate. If markets expect rates to stay higher for longer, swap rates may rise and fixed mortgage deals can become more expensive. If markets expect future rates to fall, swap rates may fall and lenders may have room to reduce fixed mortgage pricing.</p>
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
                            <th class="px-4 py-3 font-semibold text-zinc-700">Daily change</th>
                            <th class="px-4 py-3 font-semibold text-zinc-700">As at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($currentRatesTable as $row)
                            <tr>
                                <td class="px-4 py-3 text-zinc-900">{{ $row['term'] }}</td>
                                <td class="px-4 py-3 text-zinc-900">{{ $formatRate($row['rate']) }}</td>
                                <td class="px-4 py-3 font-medium {{ $basisPointClass($row['daily_change']) }}">{{ $formatBasisPoints($row['daily_change']) }}</td>
                                <td class="px-4 py-3 text-zinc-600">{{ $row['rate_date'] instanceof \Carbon\CarbonInterface ? $row['rate_date']->format('d M Y') : 'No data available' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-sm text-zinc-500">Updated daily on UK business days when new Bank of England data is available.</p>
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
