@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-12">

    {{-- HERO SECTION: Displays latest wage growth statistics and trends --}}
    <section class="relative z-0 overflow-hidden rounded-lg border border-gray-200 bg-white/80 p-6 md:p-8 shadow-sm mb-8 flex flex-col md:flex-row justify-between items-center">
        @include('partials.hero-background')
        <div class="max-w-3xl">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-900">UK Wage Growth</h1>

            {{-- Latest 3-month average year-over-year wage growth --}}
            @if($latest && $latest->three_month_avg_yoy !== null)
                <p class="mt-2 text-sm leading-6 text-gray-700">
                    Latest (3‑month average YoY):
                    <span class="font-semibold">{{ number_format($latest->three_month_avg_yoy, 1) }}%</span>
                    <span class="text-gray-600">for</span>
                    <span class="font-medium">{{ $latest->date->format('M Y') }}</span>.
                </p>
            @endif

            {{-- Month-on-month change calculations and display --}}
            @if($latest && $previous && $latest->three_month_avg_yoy !== null && $previous->three_month_avg_yoy !== null)
                @php
                    $delta_three = $latest->three_month_avg_yoy - $previous->three_month_avg_yoy;
                @endphp

                <p class="mt-1 text-sm leading-6 text-gray-700">
                    Month‑on‑month (3‑month average):
                    <span class="font-semibold {{ $delta_three > 0 ? 'text-emerald-700' : ($delta_three < 0 ? 'text-red-700' : 'text-zinc-900') }}">
                        @if($delta_three > 0)
                            +{{ number_format(abs($delta_three), 1) }}%
                        @elseif($delta_three < 0)
                            -{{ number_format(abs($delta_three), 1) }}%
                        @else
                            No change
                        @endif
                    </span>
                </p>
            @endif
        </div>

        {{-- Hero image --}}
        <div class="mt-6 md:mt-0 md:ml-8 flex-shrink-0">
            <img src="{{ asset('assets/images/site/wage_growth.jpg') }}" 
                 alt="Wage Growth" 
                 class="w-90 h-auto">
        </div>
    </section>

    {{-- MAIN CHART: 3-month average wage growth history --}}
    <section class="mb-6">
        <div class="border p-4 bg-white rounded-lg shadow">
            <div class="mb-2 text-sm font-medium text-gray-700">
                Wage Growth — 3‑Month Average
            </div>
            <div class="h-96">
                <canvas id="wageChart"></canvas>
            </div>
        </div>
    </section>

    {{-- BONUS EFFECT EXPLANATION: Collapsible panel explaining how to interpret the data --}}
    <section class="mb-6">
        <details class="group rounded-lg border border-amber-200 bg-amber-50 shadow-sm">
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-amber-900 flex items-center justify-between">
                How to interpret the trend
                <span class="text-xs text-amber-700 ml-3 group-open:hidden">Show</span>
                <span class="text-xs text-red-600 ml-3 hidden group-open:inline">Hide</span>
            </summary>
            
            <div class="px-5 pb-5 pt-3 text-sm text-zinc-800">
                <p>
                    The <span class="font-semibold">3-month average</span> smooths out noisy monthly moves and gives a cleaner read on pay momentum.
                    Rising values suggest earnings pressure is building, while falling values suggest the labour market is cooling.
                </p>
                
                <p class="mt-2">
                    Large shifts in wage growth tend to line up with major economic shocks when employers pull back on pay growth,
                    overtime, or hiring:
                </p>
                
                <ul class="mt-2 list-disc pl-5 space-y-1">
                    <li>
                        <span class="font-medium">2008‑2009 — Global financial crisis:</span>
                        bank failures, credit tightening and a deep recession saw significant cuts to banking and financial-sector bonuses.
                    </li>
                    <li>
                        <span class="font-medium">2020‑2021 — COVID‑19 shock:</span>
                        lockdowns, furlough schemes and widespread uncertainty led many employers to freeze or reduce bonuses even where
                        base pay held up.
                    </li>
                    <li>
                        <span class="font-medium">Other dips:</span>
                        smaller negative moves can reflect sector-specific slowdowns, hiring freezes or one-off shocks that reduce
                        variable pay before they show up more broadly in unemployment.
                    </li>
                </ul>
                
                <p class="mt-2 text-xs text-amber-900">
                    In short: a sustained slowdown in wage growth can feed through to housing demand and mortgage affordability with a lag.
                </p>
            </div>
        </details>
    </section>

    {{-- SUMMARY CARDS: Key statistics at a glance --}}
    @php
        $maxThree = $all->max('three_month_avg_yoy');
        $minThree = $all->min('three_month_avg_yoy');
        
        $maxThreeRow = $all->firstWhere('three_month_avg_yoy', $maxThree);
        $minThreeRow = $all->firstWhere('three_month_avg_yoy', $minThree);
    @endphp

    <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-gray-500">Highest 3‑Month Avg YoY</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($maxThree, 1) }}%</div>
            <div class="text-sm text-gray-600">in {{ $maxThreeRow->date->format('M Y') }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-gray-500">Lowest 3‑Month Avg YoY</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($minThree, 1) }}%</div>
            <div class="text-sm text-gray-600">in {{ $minThreeRow->date->format('M Y') }}</div>
        </div>
    </section>

    {{-- DATA TABLE: Complete historical wage growth data --}}
    <div class="overflow-hidden border-gray-200 bg-white shadow-sm rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="border-b border-gray-200 px-4 py-2">Month</th>
                        <th class="border-b border-gray-200 px-4 py-2">3‑Month Avg YoY (%)</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($all->sortByDesc('date') as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="border-b border-gray-100 px-4 py-2">
                                {{ $row->date->format('M Y') }}
                            </td>
                            <td class="border-b border-gray-100 px-4 py-2 font-medium">
                                {{ number_format($row->three_month_avg_yoy, 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-gray-500">
                                No data available.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Chart.js library for rendering the wage growth chart --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>

<script>
(function() {
    const labels = @json($labels);
    const three  = @json($values_three);

    const canvas = document.getElementById('wageChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    if (window._wageChart) {
        window._wageChart.destroy();
    }

    if (canvas.parentElement) {
        canvas.height = canvas.parentElement.clientHeight;
    }

    window._wageChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '3-month avg YoY %',
                    data: three,
                    borderColor: 'rgba(30, 30, 30, 1)',
                    backgroundColor: 'rgba(30, 30, 30, 0.20)',
                    tension: 0.15,
                    pointRadius: 0,
                    borderWidth: 2,
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { 
                    display: true 
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(ctx) {
                            const v = ctx.parsed.y;
                            if (v === null || v === undefined) {
                                return ' n/a';
                            }
                            return ' ' + v.toFixed(1) + '%';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: function(value, index) {
                            const raw = this.getLabelForValue(value);
                            return raw.slice(0, 4);
                        },
                        maxTicksLimit: 14
                    }
                },
                y: {
                    beginAtZero: false,
                    grace: '5%'
                }
            }
        }
    });
})();
</script>
@endsection
