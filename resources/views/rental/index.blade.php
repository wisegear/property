@extends('layouts.app')
@include('partials.chartjs-head')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 md:py-10">
    {{-- Hero / summary card --}}
    <section class="relative z-0 mb-8 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm md:flex md:flex-row md:items-center md:justify-between md:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 max-w-4xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                Rental Trends
            </div>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-900 md:text-3xl">Rental Dashboard</h1>
            <p class="mt-4 text-sm leading-6 text-zinc-600">
                <span class="font-semibold">Quarterly rental costs and changes for UK and each nation.</span>
            </p>
            <p class="mt-2 text-sm leading-6 text-zinc-600">
                Charts show average rent levels alongside quarter-on-quarter percentage changes.  click on the butons below to isolate England, Scotland, Wales, or Northern Ireland data.
                Information shows average overall and then splits it down into 1,2,3, and 4+ bedroom properties.  Alsco covers detached, semi-detached, terraced, and flats.
            </p>
            @if($latestPeriod)
                <p class="mt-2 text-sm leading-6 text-zinc-600">
                    Latest data: <span class="font-semibold">{{ $latestPeriod }}</span>
                </p>
            @endif
            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('rental.england') }}" class="inner-button">
                    England
                </a>
                <a href="{{ route('rental.scotland') }}" class="inner-button">
                    Scotland
                </a>
                <a href="{{ route('rental.wales') }}" class="inner-button">
                    Wales
                </a>
                <a href="{{ route('rental.northern-ireland') }}" class="inner-button">
                    Northern Ireland
                </a>
            </div>
        </div>
        <div class="relative z-10 mt-6 flex-shrink-0 md:mt-0 md:ml-8">
            <img src="{{ asset('assets/images/site/rental.jpg') }}" alt="Rental dashboard" class="w-80 h-auto">
        </div>
    </section>

    <h2 class="mt-8 text-xl font-semibold text-zinc-900">Quarterly Rent Change by Nation &amp; UK</h2>
    <p class="mb-4 text-sm text-zinc-600">Rental price line uses pounds; monthly change line uses percentage.</p>

    {{-- UK chart --}}
    @if(isset($seriesByArea[0]))
        <article class="mb-6 min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Rental Trend</p>
                    <h3 class="mt-2 text-xl font-semibold text-zinc-900">{{ $seriesByArea[0]['name'] }}</h3>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Quarterly</span>
            </div>
            <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                <canvas id="rentalChart0" aria-label="{{ $seriesByArea[0]['name'] }} rental change" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>
    @endif

    {{-- Nation charts --}}
    <div class="grid gap-6 md:grid-cols-2">
        @foreach($seriesByArea as $i => $s)
            @continue($i === 0)
            <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Rental Trend</p>
                        <h3 class="mt-2 text-xl font-semibold text-zinc-900">{{ $s['name'] }}</h3>
                    </div>
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Quarterly</span>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="rentalChart{{ $i }}" aria-label="{{ $s['name'] }} rental change" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>
        @endforeach
    </div>
</div>

<script>
(function () {
    try {
        const series = @json($seriesByArea);
        const PRICE = '#2563eb';
        const CHANGE = '#16a34a';
        const chartGridColor = 'rgba(113, 113, 122, 0.12)';
        const chartBorderColor = 'rgba(113, 113, 122, 0.22)';
        const chartTickColor = '#52525b';
        const chartLegendColor = '#3f3f46';

        const formatQuarterTick = (value, index, ticks, scale) => {
            const label = scale.getLabelForValue(value);

            if (!label) {
                return '';
            }

            const quarterMatch = label.match(/^(\d{4})-Q([1-4])$/);
            const isLastTick = index === ticks.length - 1;

            if (!quarterMatch) {
                return isLastTick ? label : '';
            }

            const [, year, quarter] = quarterMatch;

            if (quarter === '1' || isLastTick) {
                return year;
            }

            return '';
        };

        if (!Array.isArray(series) || series.length === 0) {
            console.warn('seriesByArea is empty or missing');
            return;
        }

        series.forEach((s, i) => {
            const el = document.getElementById('rentalChart' + i);
            if (!el) return;

            const labels = s.labels || [];
            const prices = s.prices || [];
            const changes = s.changes || [];

            if (!labels.length) return;

            new Chart(el.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Rental price',
                            data: prices,
                            yAxisID: 'y1',
                            borderColor: PRICE,
                            backgroundColor: 'rgba(37, 99, 235, 0.12)',
                            spanGaps: true,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                            borderWidth: 2,
                            tension: 0.28,
                            fill: true,
                        },
                        {
                            label: 'Quarterly change',
                            data: changes,
                            yAxisID: 'y',
                            borderColor: CHANGE,
                            backgroundColor: 'rgba(22, 163, 74, 0.10)',
                            spanGaps: true,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                            borderWidth: 2,
                            tension: 0.28,
                            fill: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                boxWidth: 10,
                                boxHeight: 10,
                                color: chartLegendColor,
                            },
                        },
                        tooltip: {
                            backgroundColor: 'rgba(24, 24, 27, 0.94)',
                            titleColor: '#fafafa',
                            bodyColor: '#f4f4f5',
                            borderColor: 'rgba(161, 161, 170, 0.35)',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function (context) {
                                    const dsLabel = context.dataset.label || '';
                                    const value = context.parsed.y;
                                    if (context.dataset.yAxisID === 'y1') {
                                        try {
                                            return dsLabel + ': £' + value.toLocaleString('en-GB', { maximumFractionDigits: 0 });
                                        } catch (e) {
                                            return dsLabel + ': £' + value;
                                        }
                                    }
                                    try {
                                        return dsLabel + ': ' + value.toLocaleString('en-GB', { maximumFractionDigits: 2 }) + '%';
                                    } catch (e) {
                                        return dsLabel + ': ' + value + '%';
                                    }
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { color: chartBorderColor },
                            ticks: {
                                color: chartTickColor,
                                autoSkip: false,
                                maxRotation: 0,
                                minRotation: 0,
                                callback: function (value, index, ticks) {
                                    return formatQuarterTick(value, index, ticks, this);
                                },
                            },
                        },
                        y: {
                            position: 'left',
                            grid: { color: chartGridColor, drawBorder: false },
                            border: { color: chartBorderColor },
                            title: {
                                display: true,
                                text: 'Quarterly change (%)',
                            },
                            ticks: {
                                color: chartTickColor,
                                callback: function (value) {
                                    return value + '%';
                                },
                            },
                        },
                        y1: {
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Rental price (£)',
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            border: { color: chartBorderColor },
                            ticks: {
                                color: chartTickColor,
                                callback: function (value) {
                                    try {
                                        return '£' + value.toLocaleString('en-GB', { maximumFractionDigits: 0 });
                                    } catch (e) {
                                        return value;
                                    }
                                },
                            },
                        },
                    },
                },
            });
        });
    } catch (e) {
        console.error('Rental chart init error', e);
    }
})();
</script>
@endsection
