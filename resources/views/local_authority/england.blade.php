@extends('layouts.app')
@include('partials.chartjs-head')

@section('content')
    {{-- Hero --}}
    <section class="relative z-0 -mx-6 -mt-6 overflow-hidden bg-white py-8 shadow-[0_1px_0_rgba(0,0,0,0.06)] md:py-9">
      <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[minmax(0,1fr)_minmax(280px,0.42fr)] md:gap-8">
        <div class="max-w-5xl">
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">
                <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                Social Housing
            </div>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">
                English Council Housing Stock Figures
            </h1>
            <p class="mt-3 text-sm text-zinc-500">
                This is the most accurate official data I can provide.  The data can be split down by larger regions rather than smaller ones.  This as always is due to the data and/or quality of it. I am
                working on getting more granular data in future updates.
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-zinc-600">
                <span class="inline-flex items-center gap-2 rounded-sm border border-zinc-200 bg-white/70 px-3 py-1">
                    <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                    Latest data<datalist></datalist>: <class="">July 2026
                </span>
                <span class="inline-flex items-center gap-2 rounded-sm border border-zinc-200 bg-white/70 px-3 py-1">
                    <span class="h-2 w-2 rounded-full bg-zinc-400"></span>
                    Next update Expected: <class="">June 2027
                </span>
            </div>
        </div>
        <div class="hidden justify-self-end md:block">
            <img src="{{ asset('/assets/images/site/houses.jpg') }}" alt="Property Research" class="h-44 w-full max-w-sm object-cover [mask-image:linear-gradient(to_right,transparent,black_22%)]">
        </div>
      </div>
    </section>
<div class="mx-auto max-w-7xl px-4 py-8">

    {{-- Controls --}}
    <section class="mx-auto w-full max-w-2xl">
        <div class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col items-center justify-center">
                <label for="regionSelect" class="block text-sm text-zinc-700">
                    Use the dropdown to see specific regional data
                </label>

                <div class="mt-3 w-full max-w-md">
                    <select id="regionSelect"
                            class="w-full rounded border border-zinc-300 bg-white px-2 py-1 text-zinc-900 text-sm shadow-sm focus:border-lime-500 focus:ring-lime-500">
                        @foreach($regions as $region)
                            <option value="{{ $region }}">{{ $region }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </section>

    {{-- Charts --}}
    <div class="mt-8 grid grid-cols-1 gap-8">
        {{-- Full-width total stock chart --}}
        <article class="min-w-0 overflow-hidden rounded-sm border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Council Stock</p>
                    <h2 id="stockTitle" class="mt-2 text-xl font-semibold text-zinc-900">Total council housing stock</h2>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Selected region</span>
            </div>
            <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                <canvas id="stockChart" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>

        {{-- Two charts side-by-side --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <article class="min-w-0 overflow-hidden rounded-sm border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Council Stock</p>
                        <h2 id="newBuildsTitle" class="mt-2 text-xl font-semibold text-zinc-900">New builds</h2>
                    </div>
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Selected region</span>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="newBuildsChart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>

            <article class="min-w-0 overflow-hidden rounded-sm border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Council Stock</p>
                        <h2 id="acquisitionsTitle" class="mt-2 text-xl font-semibold text-zinc-900">Acquisitions</h2>
                    </div>
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Selected region</span>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="acquisitionsChart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>
        </div>
    </div>

    {{-- England-wide charts --}}
    <div class="mt-8 grid grid-cols-1 gap-8">
        <article class="min-w-0 overflow-hidden rounded-sm border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">National View</p>
                    <h2 class="mt-2 text-xl font-semibold text-zinc-900">All England – total council housing stock</h2>
                </div>
                <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">All regions</span>
            </div>
            <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                <canvas id="englandStockChart" class="block h-full w-full max-w-full"></canvas>
            </div>
        </article>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <article class="min-w-0 overflow-hidden rounded-sm border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">National View</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">All England – new builds</h2>
                    </div>
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">All regions</span>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="englandNewBuildsChart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>

            <article class="min-w-0 overflow-hidden rounded-sm border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">National View</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">All England – acquisitions</h2>
                    </div>
                    <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">All regions</span>
                </div>
                <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
                    <canvas id="englandAcquisitionsChart" class="block h-full w-full max-w-full"></canvas>
                </div>
            </article>
        </div>
    </div>

    {{-- Movers tables --}}
    <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
        <div class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Top declines ({{ $baselineYear }} → {{ $compareYear }})</h2>
            <table class="min-w-full text-sm">
                <thead class="border-b">
                    <tr class="text-left">
                        <th class="py-2">Region</th>
                        <th class="py-2 text-right">Start</th>
                        <th class="py-2 text-right">End</th>
                        <th class="py-2 text-right">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($biggestDeclines as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2">{{ $row['region'] }}</td>
                            <td class="py-2 text-right">{{ number_format($row['start_stock']) }}</td>
                            <td class="py-2 text-right">{{ number_format($row['end_stock']) }}</td>
                            <td class="py-2 text-right text-rose-600">{{ number_format($row['delta']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Top increases ({{ $baselineYear }} → {{ $compareYear }})</h2>
            <p class="text-sm text-zinc-500 mb-3">Correct, no region has seen an overall increase in stock.  Unlike Scotland, Right to Buy still exists and stock is depleting
                faster than it's growing.
            </p>
            <table class="min-w-full text-sm">
                <thead class="border-b">
                    <tr class="text-left">
                        <th class="py-2">Region</th>
                        <th class="py-2 text-right">Start</th>
                        <th class="py-2 text-right">End</th>
                        <th class="py-2 text-right">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($biggestIncreases as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2">{{ $row['region'] }}</td>
                            <td class="py-2 text-right">{{ number_format($row['start_stock']) }}</td>
                            <td class="py-2 text-right">{{ number_format($row['end_stock']) }}</td>
                            <td class="py-2 text-right text-lime-600">+{{ number_format($row['delta']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Movers tables: last 5 years --}}
    <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
        <div class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Top declines (last 5 years: {{ $baselineYearRecent }} → {{ $compareYear }})</h2>
            <table class="min-w-full text-sm">
                <thead class="border-b">
                    <tr class="text-left">
                        <th class="py-2">Region</th>
                        <th class="py-2 text-right">Start</th>
                        <th class="py-2 text-right">End</th>
                        <th class="py-2 text-right">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($biggestDeclinesRecent as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2">{{ $row['region'] }}</td>
                            <td class="py-2 text-right">{{ number_format($row['start_stock']) }}</td>
                            <td class="py-2 text-right">{{ number_format($row['end_stock']) }}</td>
                            <td class="py-2 text-right text-rose-600">{{ number_format($row['delta']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Top increases (last 5 years: {{ $baselineYearRecent }} → {{ $compareYear }})</h2>
            <table class="min-w-full text-sm">
                <thead class="border-b">
                    <tr class="text-left">
                        <th class="py-2">Region</th>
                        <th class="py-2 text-right">Start</th>
                        <th class="py-2 text-right">End</th>
                        <th class="py-2 text-right">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($biggestIncreasesRecent as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2">{{ $row['region'] }}</td>
                            <td class="py-2 text-right">{{ number_format($row['start_stock']) }}</td>
                            <td class="py-2 text-right">{{ number_format($row['end_stock']) }}</td>
                            <td class="py-2 text-right text-lime-600">+{{ number_format($row['delta']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    const years = @json($years);
    const byRegion = @json($byRegion);
    const national = @json($national);
    const chartGridColor = 'rgba(113, 113, 122, 0.12)';
    const chartBorderColor = 'rgba(113, 113, 122, 0.22)';
    const chartTickColor = '#52525b';

    function buildNationalSeries(key) {
        return years.map(y => national[y]?.[key] ?? null);
    }

    // England-wide charts
    const englandStockChart = makeLineChart(
        document.getElementById('englandStockChart'),
        'England total stock',
        buildNationalSeries('total_stock')
    );

    const englandNewBuildsChart = makeLineChart(
        document.getElementById('englandNewBuildsChart'),
        'England new builds',
        buildNationalSeries('new_builds')
    );

    const englandAcquisitionsChart = makeLineChart(
        document.getElementById('englandAcquisitionsChart'),
        'England acquisitions',
        buildNationalSeries('acquisitions')
    );

    const regionSelect = document.getElementById('regionSelect');

    let stockChart, newBuildsChart, acquisitionsChart;

    function buildSeries(region, key) {
        return years.map(y => byRegion[region]?.[y]?.[key] ?? null);
    }

    function makeLineChart(ctx, label, data) {
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: years,
                datasets: [{
                    label,
                    data,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    fill: false,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.28,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor, maxRotation: 0 } },
                    y: { beginAtZero: false, grid: { color: chartGridColor, drawBorder: false }, border: { color: chartBorderColor }, ticks: { color: chartTickColor } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(24, 24, 27, 0.94)',
                        titleColor: '#fafafa',
                        bodyColor: '#f4f4f5',
                        borderColor: 'rgba(161, 161, 170, 0.35)',
                        borderWidth: 1,
                        padding: 12,
                    }
                }
            }
        });
    }

    function renderCharts(region) {
        stockChart?.destroy();
        newBuildsChart?.destroy();
        acquisitionsChart?.destroy();

        document.getElementById('stockTitle').textContent = `Total council housing stock – ${region}`;
        document.getElementById('newBuildsTitle').textContent = `New builds – ${region}`;
        document.getElementById('acquisitionsTitle').textContent = `Acquisitions – ${region}`;

        stockChart = makeLineChart(
            document.getElementById('stockChart'),
            'Total stock',
            buildSeries(region, 'total_stock')
        );

        newBuildsChart = makeLineChart(
            document.getElementById('newBuildsChart'),
            'New builds',
            buildSeries(region, 'new_builds')
        );

        acquisitionsChart = makeLineChart(
            document.getElementById('acquisitionsChart'),
            'Acquisitions',
            buildSeries(region, 'acquisitions')
        );
    }

    // Initial render
    renderCharts(regionSelect.value);

    regionSelect.addEventListener('change', e => {
        renderCharts(e.target.value);
    });
</script>
@endsection
