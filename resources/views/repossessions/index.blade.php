@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    {{-- Hero / summary card --}}
    <section class="relative z-0 mb-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm md:flex md:flex-row md:items-center md:justify-between md:p-8">
        @include('partials.hero-background')
        <div class="relative z-10 max-w-4xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                Court Actions
            </div>
            <h1 class="mb-2 mt-4 text-2xl font-bold tracking-tight text-zinc-900 md:text-3xl">Repossession Actions</h1>
            <p class="text-sm text-zinc-600">
                Data from 2003 to 2025 provided by the court service. Next update January 2026, data is provided quarterly. It's important to note that Repossessions are part 
                of a long process, only the possession action title "Repossession" reflects a property being repossessed.
            </p>
            <p class="pt-1 text-sm text-zinc-600">
                This section covers <span class="font-bold">England & Wales</span> only.
            </p>            
        </div>
        <div class="relative z-10 mt-6 flex-shrink-0 md:mt-0 md:ml-8">
            <img src="{{ asset('assets/images/site/repo.jpg') }}" alt="Repossessions" class="w-72 h-auto">
        </div>
    </section>

    {{-- Summary panels --}}
    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Total Claims Year To Date</div>
            <div class="mt-3 text-2xl font-semibold text-zinc-900">
                {{ (int)($latest->year ?? 0) }}
                <span class="ml-2 text-sm text-zinc-500">{{ number_format((int)($latest->total ?? 0)) }}</span>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Total Claims In The Previous Year</div>
            <div class="mt-3 text-2xl font-semibold text-zinc-900">
                {{ (int)($previous->year ?? 0) }}
                <span class="ml-2 text-sm text-zinc-500">{{ number_format((int)($previous->total ?? 0)) }}</span>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Year-On-Year Change</div>
            <div class="mt-3 text-2xl font-semibold {{ ($yoy ?? 0) < 0 ? 'text-lime-700' : 'text-rose-700' }}">
                {{ $yoy === null ? '—' : (($yoy >= 0 ? '+' : '') . number_format((int)$yoy)) }}
            </div>
        </div>
    </div>

    {{-- Local authority search --}}
    <div class="mb-8 flex justify-center">
        <div class="relative w-full max-w-xl rounded-lg border border-lime-200 bg-zinc-100 p-4 shadow-sm">
            <label for="laSearch" class="mb-2 block text-sm font-medium text-zinc-800">
                Find repossession data for a specific local authority
            </label>
            <input
                id="laSearch"
                type="text"
                placeholder="Start typing a local authority name, e.g. Birmingham, Manchester, Leeds"
                autocomplete="off"
                class="w-full rounded-md border border-lime-300 px-4 py-3 text-sm bg-white shadow
                       focus:border-lime-500 focus:ring focus:ring-lime-300"
            />
            <ul
                id="laResults"
                class="absolute z-20 mt-1 hidden w-full rounded-md border border-gray-200
                       bg-white shadow-lg"
            ></ul>
        </div>
    </div>

    {{-- Total repossessions (yearly) --}}
    <section class="mb-6 min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Repossessions</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Total number of actions</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Annual totals</span>
        </div>
        <div class="mt-6 h-80 min-w-0 overflow-hidden">
            <canvas id="reposTotalChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </section>

    {{-- Charts --}}
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
                <canvas id="reposTypeChart" class="block h-full w-full max-w-full"></canvas>
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
                <canvas id="reposActionChart" class="block h-full w-full max-w-full"></canvas>
            </div>
            <p class="mt-3 text-xs text-zinc-500">Showing top actions overall + “Other” to keep the chart readable desipte there being virtually none.</p>
        </section>
    </div>

    {{-- Local authority tables (actions) --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Breakdown by who raised them (possession_type) --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="px-2 py-3">
                <div class="text-sm font-medium text-gray-700">20 local authorities where the most actions were raised</div>
                <div class="text-xs text-gray-500 mt-0.5">Split by Lender and Landlord Type.</div>
            </div>
            <div class="overflow-x-auto p-2">
                <table class="min-w-full">
                    <thead class="bg-gray-50 text-left text-sm text-gray-600">
                        <tr>
                            <th class="px-4 py-2">Local authority</th>
                            <th class="px-4 py-2 text-right">Accelerated</th>
                            <th class="px-4 py-2 text-right">Mortgage</th>
                            <th class="px-4 py-2 text-right">Private</th>
                            <th class="px-4 py-2 text-right">Social</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($la_breakdown_rows as $r)
                            <tr class="hover:bg-gray-50">
                                <td class="border-b border-zinc-200 px-4 py-2">{{ $r['local_authority'] }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Accelerated_Landlord']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Mortgage']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Private_Landlord']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Social_Landlord']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right font-medium">{{ number_format((int)$r['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Bottom 20 by actions (least actions) --}}
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="px-2 py-3">
                <div class="text-sm font-medium text-gray-700">20 local authorities where the least actions were raised</div>
                <div class="text-xs text-gray-500 mt-0.5">Split by Lender and Landlord Type.</div>
            </div>
            <div class="overflow-x-auto p-2">
                <table class="min-w-full">
                    <thead class="bg-gray-50 text-left text-sm text-gray-600">
                        <tr>
                            <th class="px-4 py-2">Local authority</th>
                            <th class="px-4 py-2 text-right">Accelerated</th>
                            <th class="px-4 py-2 text-right">Mortgage</th>
                            <th class="px-4 py-2 text-right">Private</th>
                            <th class="px-4 py-2 text-right">Social</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($la_breakdown_least_rows as $r)
                            <tr class="hover:bg-gray-50">
                                <td class="border-b border-zinc-200 px-4 py-2">{{ $r['local_authority'] }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Accelerated_Landlord']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Mortgage']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Private_Landlord']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right">{{ number_format((int)$r['Social_Landlord']) }}</td>
                                <td class="border-b border-zinc-200 px-4 py-2 text-right font-medium">{{ number_format((int)$r['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const labels = @json($year_labels);
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

    // 1) Total (yearly)
    const totals = @json($year_totals);
    const totalCtx = document.getElementById('reposTotalChart').getContext('2d');
    if (window._reposTotalChart) window._reposTotalChart.destroy();
    window._reposTotalChart = new Chart(totalCtx, {
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

    // 2) Types (yearly)
    const typeSeries = @json($type_series);
    const typeDatasets = Object.keys(typeSeries).map(k => ({
        label: k.replaceAll('_',' '),
        data: typeSeries[k],
        tension: 0.28,
        pointRadius: 2,
        pointHoverRadius: 4,
        pointStyle: 'circle',
    }));

    const typeCtx = document.getElementById('reposTypeChart').getContext('2d');
    if (window._reposTypeChart) window._reposTypeChart.destroy();
    window._reposTypeChart = new Chart(typeCtx, {
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

    // 3) Actions (yearly) - stacked bar
    const actionSeries = @json($action_series);
    const actionDatasets = Object.keys(actionSeries).map(k => ({
        label: k.replaceAll('_',' '),
        data: actionSeries[k],
        stack: 'actions',
        borderRadius: 8,
    }));

    const actionCtx = document.getElementById('reposActionChart').getContext('2d');
    if (window._reposActionChart) window._reposActionChart.destroy();
    window._reposActionChart = new Chart(actionCtx, {
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
})();

// Local authority search (client-side JSON lookup)
(async function () {
    try {
        const res = await fetch('/data/repo_local_authorities.json');
        if (!res.ok) return;

        const authorities = await res.json();
        const input = document.getElementById('laSearch');
        const results = document.getElementById('laResults');

        if (!input || !results) return;

        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            results.innerHTML = '';

            if (!q) {
                results.classList.add('hidden');
                return;
            }

            const matches = authorities
                .filter(a => a.label.toLowerCase().includes(q))
                .slice(0, 10);

            if (!matches.length) {
                results.classList.add('hidden');
                return;
            }

            for (const m of matches) {
                const li = document.createElement('li');
                li.textContent = m.label;
                li.className = 'cursor-pointer px-4 py-2 text-sm hover:bg-gray-100';
                li.onclick = () => window.location.href = m.path;
                results.appendChild(li);
            }

            results.classList.remove('hidden');
        });

        // Hide dropdown when clicking elsewhere
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#laSearch')) {
                results.classList.add('hidden');
            }
        });
    } catch (e) {
        // Fail silently – charts still load
    }
})();

</script>
@endsection
