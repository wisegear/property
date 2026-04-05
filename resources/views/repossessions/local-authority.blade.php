@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-12">
    {{-- Hero --}}
    <section class="relative z-0 mb-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm md:flex md:flex-row md:items-center md:justify-between md:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 max-w-4xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                Court Actions
            </div>
            <h1 class="mb-2 mt-4 text-2xl font-bold tracking-tight text-zinc-900 md:text-3xl">
                Repossession claims – <span class="text-lime-600">{{ $local_authority }}</span>
            </h1>
            <p class="text-sm text-zinc-600">
                Data from 2003 to 2025 provided by the court service. Next update January 2026, data is provided quarterly. It's important to note that Repossessions are part 
                of a long process, only the possession action title "Repossession" reflects a property being repossessed.
            </p>
        </div>
        <div class="relative z-10 mt-6 flex-shrink-0 md:mt-0 md:ml-8">
            <img src="{{ asset('assets/images/site/repo.jpg') }}" alt="Repossessions" class="w-72 h-auto">
        </div>
    </section>

    @php
        // Year labels + totals
        $labels = collect($yearly)->pluck('year')->map(fn($y) => (int)$y)->values();
        $totals = collect($yearly)->pluck('total')->map(fn($v) => (int)$v)->values();

        // Types: fixed order
        $typeOrder = ['Accelerated_Landlord','Mortgage','Private_Landlord','Social_Landlord'];
        $typeSeries = [];
        foreach ($typeOrder as $t) {
            $typeSeries[$t] = array_fill(0, $labels->count(), 0);
        }
        $yearIndex = $labels->flip();
        foreach (collect($byType) as $r) {
            $y = (int)($r->year ?? 0);
            $t = (string)($r->possession_type ?? '');
            if ($t === '' || !$yearIndex->has($y) || !isset($typeSeries[$t])) continue;
            $typeSeries[$t][$yearIndex[$y]] = (int)($r->total ?? 0);
        }

        // Actions: keep readable – top 8 overall + Other
        $topN = 8;
        $actionTotals = collect($byAction)
            ->groupBy(fn($r) => trim((string)($r->possession_action ?? '')))
            ->map(fn($rows) => (int)collect($rows)->sum('total'))
            ->filter(fn($v, $k) => trim((string)$k) !== '')
            ->sortDesc();

        $topActions = $actionTotals->keys()->take($topN)->values()->all();

        $actionSeries = [];
        foreach ($topActions as $a) {
            $actionSeries[$a] = array_fill(0, $labels->count(), 0);
        }
        $actionSeries['Other'] = array_fill(0, $labels->count(), 0);

        foreach (collect($byAction) as $r) {
            $y = (int)($r->year ?? 0);
            $a = trim((string)($r->possession_action ?? ''));
            if ($a === '' || !$yearIndex->has($y)) continue;
            $idx = $yearIndex[$y];
            $val = (int)($r->total ?? 0);
            if (in_array($a, $topActions, true)) {
                $actionSeries[$a][$idx] = $val;
            } else {
                $actionSeries['Other'][$idx] += $val;
            }
        }

        // Summary
        $latestTotal = $totals->last();
        $prevTotal = $totals->count() > 1 ? $totals[$totals->count() - 2] : null;
        $latestYear = $labels->last();
        $prevYear = $labels->count() > 1 ? $labels[$labels->count() - 2] : null;
        $yoy = ($latestTotal !== null && $prevTotal !== null) ? ((int)$latestTotal - (int)$prevTotal) : null;
    @endphp

    {{-- Summary panels --}}
    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Total Claims Year To Date</div>
            <div class="mt-3 text-2xl font-semibold text-zinc-900">
                {{ (int)($latestYear ?? 0) }}
                <span class="ml-2 text-sm text-zinc-500">{{ number_format((int)($latestTotal ?? 0)) }}</span>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Total Claims In The Previous Year</div>
            <div class="mt-3 text-2xl font-semibold text-zinc-900">
                {{ (int)($prevYear ?? 0) }}
                <span class="ml-2 text-sm text-zinc-500">{{ number_format((int)($prevTotal ?? 0)) }}</span>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Year-On-Year Change</div>
            <div class="mt-3 text-2xl font-semibold {{ ($yoy ?? 0) < 0 ? 'text-lime-600' : 'text-rose-600' }}">
                {{ $yoy === null ? '—' : (($yoy >= 0 ? '+' : '') . number_format((int)$yoy)) }}
            </div>
        </div>
    </div>

    {{-- Charts --}}

    {{-- Total cases (yearly) --}}
    <section class="mb-6 min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Repossessions</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Total number of actions</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Annual totals</span>
        </div>
        <div class="mt-6 h-80 min-w-0 overflow-hidden">
            <canvas id="laTotalChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6">
        {{-- Possession type (yearly) --}}
        <section class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Breakdown</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">Who is raising actions?</h2>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Annual totals</span>
            </div>
            <div class="mt-6 h-80 min-w-0 overflow-hidden">
                <canvas id="laTypeChart" class="block h-full w-full max-w-full"></canvas>
            </div>
            <p class="mt-3 text-xs text-zinc-500">Types: Accelerated Landlord, Mortgage, Private Landlord, Social Landlord.</p>
        </section>

        {{-- Possession action (yearly) --}}
        <section class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Breakdown</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">Possession actions</h2>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Stacked totals</span>
            </div>
            <div class="mt-6 h-80 min-w-0 overflow-hidden">
                <canvas id="laActionChart" class="block h-full w-full max-w-full"></canvas>
            </div>
            <p class="mt-3 text-xs text-zinc-500">Showing top actions overall + “Other” to keep the chart readable desipte there being virtually none.</p>
        </section>
    </div>
</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labels = @json($labels);
    const chartGridColor = 'rgba(113, 113, 122, 0.12)';
    const chartBorderColor = 'rgba(113, 113, 122, 0.22)';
    const chartTickColor = '#52525b';
    const chartLegendColor = '#3f3f46';
    const tooltipBase = {
        backgroundColor: 'rgba(24, 24, 27, 0.94)',
        titleColor: '#fafafa',
        bodyColor: '#f4f4f5',
        borderColor: 'rgba(161, 161, 170, 0.35)',
        borderWidth: 1,
        padding: 12,
    };

    // Types
    const typeSeries = @json($typeSeries);
    const typeDatasets = Object.keys(typeSeries).map(k => ({
        label: k.replaceAll('_',' '),
        data: typeSeries[k],
        tension: 0.28,
        pointRadius: 2,
        pointHoverRadius: 4,
        pointStyle: 'circle',
    }));

    const typeCtx = document.getElementById('laTypeChart')?.getContext('2d');
    if (typeCtx) {
        new Chart(typeCtx, {
            type: 'line',
            data: { labels, datasets: typeDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10, boxHeight: 10, color: chartLegendColor } },
                    tooltip: { ...tooltipBase, mode: 'index', intersect: false },
                },
                scales: {
                    x: { grid: { display: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor, maxRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, grace: '5%', grid: { color: chartGridColor, drawBorder: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor } }
                }
            }
        });
    }

    // Total
    const totals = @json($totals);
    const totalCtx = document.getElementById('laTotalChart')?.getContext('2d');
    if (totalCtx) {
        new Chart(totalCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Total',
                    data: totals,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    fill: true,
                    tension: 0.28,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    pointStyle: 'circle',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { ...tooltipBase, mode: 'index', intersect: false },
                },
                scales: {
                    x: { grid: { display: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor, maxRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, grace: '5%', grid: { color: chartGridColor, drawBorder: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor } }
                }
            }
        });
    }

    // Actions (stacked)
    const actionSeries = @json($actionSeries);
    const actionDatasets = Object.keys(actionSeries).map(k => ({
        label: k.replaceAll('_',' '),
        data: actionSeries[k],
        stack: 'actions',
        borderRadius: 8,
    }));

    const actionCtx = document.getElementById('laActionChart')?.getContext('2d');
    if (actionCtx) {
        new Chart(actionCtx, {
            type: 'bar',
            data: { labels, datasets: actionDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10, boxHeight: 10, color: chartLegendColor } },
                    tooltip: { ...tooltipBase, mode: 'index', intersect: false },
                },
                scales: {
                    x: { stacked: true, grid: { display: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor, maxRotation: 0, autoSkip: true } },
                    y: { stacked: true, beginAtZero: true, grace: '5%', grid: { color: chartGridColor, drawBorder: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor } }
                }
            }
        });
    }
})();
</script>
@endsection
