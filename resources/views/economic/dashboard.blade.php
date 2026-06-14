@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-12">
    <section class="relative mb-8 overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-white via-zinc-50 to-lime-50/40 p-6 shadow-sm md:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-4xl">
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 md:text-3xl">
                    Economic Dashboard
                </h1>
                <p class="mt-2 text-sm text-zinc-700">
                    A quarterly-style housing market health check for normal consumers. {{ $heroComparisonText }}
                </p>
                <p class="mt-3 text-xs text-zinc-500">
                    Last updated: {{ now()->format('j M Y') }}
                </p>
            </div>

            <div class="flex-shrink-0">
                <img src="{{ asset('assets/images/site/stress.jpg') }}"
                     alt="Economic dashboard"
                     class="w-72 max-w-full">
            </div>
        </div>
    </section>

    @include('partials.stress-score-panel', ['totalStress' => $totalStress ?? null, 'showDashboardLink' => false, 'isSticky' => false])

    <section class="mb-8 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm md:p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-zinc-500">Market signals summary</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">At-a-glance view</h2>
                <p class="mt-3 text-sm leading-6 text-zinc-700">
                    The wider housing market currently looks <span class="font-semibold text-zinc-900">{{ $summary['tone'] }}</span>. The main area to watch is <span class="font-semibold text-zinc-900">{{ $summary['main_pressure_source'] }}</span>, but this should be read alongside the wider mix of supportive, neutral and warning signals.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <div class="font-semibold text-emerald-800">Supportive</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-900">{{ $statusCounts['Supportive'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <div class="font-semibold text-amber-900">Neutral</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-950">{{ $statusCounts['Neutral'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                    <div class="font-semibold text-rose-800">Warning</div>
                    <div class="mt-1 text-2xl font-semibold text-rose-900">{{ $statusCounts['Warning'] ?? 0 }}</div>
                </div>
                <div class="rounded-xl border border-rose-300 bg-rose-100 px-4 py-3">
                    <div class="font-semibold text-rose-900">Stress</div>
                    <div class="mt-1 text-2xl font-semibold text-rose-950">{{ $statusCounts['Stress'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-10">
        <details class="group rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-zinc-900">
                How to read these signals
                <span class="text-xs font-medium text-zinc-500 group-open:hidden">Show</span>
                <span class="hidden text-xs font-medium text-zinc-500 group-open:inline">Hide</span>
            </summary>

            <div class="space-y-3 px-5 pb-5 text-sm leading-6 text-zinc-700">
                <p>
                    Each card compares the current quarter with the previous quarter. That keeps the dashboard consistent across rates, approvals, inflation, arrears, and other indicators.
                </p>
                <p>
                    Supportive means the indicator is helping the market backdrop. Neutral means conditions are broadly steady. Warning means pressure is building. Stress means pressure is more established and deserves closer attention.
                </p>
            </div>
        </details>
    </section>

    <section class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        @foreach ($cards as $card)
            <article class="overflow-hidden rounded-2xl border p-5 shadow-sm {{ $card['status']['card'] }}">
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-lg font-semibold text-zinc-900">{{ $card['title'] }}</h3>
                    <span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold {{ $card['status']['badge'] }}">
                        {{ $card['status']['label'] }}
                    </span>
                </div>

                <div class="mt-6 space-y-5">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ $card['current_heading'] }}</p>
                                <p class="mt-2 text-sm text-zinc-600">{{ $card['current_label'] }}</p>
                                <p class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950">{{ $card['current_value'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ $card['previous_heading'] }}</p>
                                <p class="mt-2 text-sm text-zinc-600">{{ $card['previous_label'] }}</p>
                                <p class="mt-2 text-xl font-semibold text-zinc-800">{{ $card['previous_value'] }}</p>
                            </div>
                        </div>

                        @if (! empty($card['supplementary']))
                            <p class="text-sm font-medium text-zinc-700">{{ $card['supplementary'] }}</p>
                        @endif

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">{{ $card['change_heading'] }}</p>
                            <p class="mt-2 flex items-center gap-2 text-sm font-semibold {{ $card['status']['change'] }}">
                                <span>{{ $card['change_arrow'] }}</span>
                                <span>{{ $card['change'] }}</span>
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Signal</p>
                            <p class="mt-2 text-base font-semibold text-zinc-900">{{ $card['signal'] }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">What this means</p>
                            <p class="mt-2 text-sm leading-6 text-zinc-700">{{ $card['meaning'] }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/70 bg-white/80 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Recent trend</p>
                        <div class="mt-4 h-36">
                            <canvas id="{{ $card['spark_id'] }}"></canvas>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $card['status']['accent'] }}"></span>
                            <p class="text-xs leading-5 text-zinc-600">Recent trend shown for context only.</p>
                        </div>
                    </div>

                </div>
            </article>
        @endforeach
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const data = @json($sparklines ?? []);
    const sparkConfig = {
        interest: { id: 'spark-interest', badWhen: 'up' },
        inflation: { id: 'spark-inflation', badWhen: 'up' },
        wages: { id: 'spark-wages', badWhen: 'down' },
        unemployment: { id: 'spark-unemployment', badWhen: 'up' },
        approvals: { id: 'spark-approvals', badWhen: 'down' },
        arrears: { id: 'spark-arrears', badWhen: 'up' },
        repossessions: { id: 'spark-repossessions', badWhen: 'up' },
        hpi: { id: 'spark-hpi', badWhen: 'down' },
    };

    function makeSpark(id, key, badWhen) {
        const element = document.getElementById(id);

        if (!element) {
            return;
        }

        const values = data[key]?.values || [];

        if (!values.length) {
            return;
        }

        const labels = data[key]?.labels || values.map((_, index) => index + 1);

        new Chart(element.getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: 'rgba(71, 85, 105, 0.9)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    tension: 0.3,
                    fill: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        displayColors: false,
                        callbacks: {
                            title: (items) => items?.[0] ? String(labels[items[0].dataIndex]) : '',
                            label: (context) => {
                                const value = context.parsed.y;
                                return value !== null && value !== undefined ? value.toFixed(2) : '';
                            },
                        },
                    },
                },
                interaction: {
                    mode: 'nearest',
                    intersect: false,
                },
                scales: {
                    x: { display: false },
                    y: { display: false },
                },
            },
        });
    }

    Object.entries(sparkConfig).forEach(([key, config]) => {
        makeSpark(config.id, key, config.badWhen);
    });
})();
</script>
@endsection
