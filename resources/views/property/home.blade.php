@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">
    {{-- Hero / summary card --}}
    <section class="relative z-0 overflow-hidden rounded-lg border border-gray-200 bg-white/80 p-6 md:p-8 shadow-sm mb-8 flex flex-col md:flex-row justify-between items-center">
        @include('partials.hero-background')
        <div class="max-w-5xl">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-900">Property Dashboard</h1>
            <p class="mt-2 text-sm leading-6 text-gray-700">Charts below use a rolling 12-month window from 1995 and ending with the latest Land Registry month ({{ isset($latestMonth) ? \Carbon\Carbon::parse($latestMonth)->format('F') : now()->format('F') }}).</p>
            <p class="mt-2 text-sm leading-6 text-gray-700">All property data includes <span class="text-lime-600 font-bold">Category A</span> sales only, these are sales at market value on an arms length basis.  <span class="text-rose-500 font-bold">Category B</span> sales are not included as they are transactions for a variety of reasons not neccessairly at 
                arms length therefore skew the data so are excluded. Read more about this <a href="/blog/category-a-vs-category-b-property-sales-what-the-land-registry-is-actually-telling-you" class="text-lime-600 hover:text-lime-700">Here.</a>All data provided from the Land Registry.</p>     
            <div class="mt-4 flex flex-wrap gap-2"> <!-- Avoids unset in css -->
                <a href="{{ route('property.search', absolute: false) }}" class="inner-button bg-lime-600! hover:bg-lime-700!">Property Search</a>
                <a href="/property/outer-prime-london" class="inner-button">Outer Prime London</a>
                <a href="/property/prime-central-london" class="inner-button">Prime Central London</a>
                <a href="/property/ultra-prime-central-london" class="inner-button">Ultra Prime Central London</a>
            </div>
        </div>
        <div class="mt-6 md:mt-0 md:ml-8 flex-shrink-0">
            <img src="{{ asset('assets/images/site/property1.jpg') }}" alt="Property dashboard" class="w-72 h-auto">
        </div>
</section>

@php
    $salesSeriesForSnapshot = collect($salesByYear ?? []);
    $medianSeriesForSnapshot = collect($avgPriceByYear ?? []);
    $currentSalesSnapshot = (float) optional($salesSeriesForSnapshot->last())->total;
    $previousSalesSnapshot = (float) optional($salesSeriesForSnapshot->slice(-2, 1)->first())->total;
    $currentMedianSnapshot = (float) optional($medianSeriesForSnapshot->last())->avg_price;
    $previousMedianSnapshot = (float) optional($medianSeriesForSnapshot->slice(-2, 1)->first())->avg_price;

    $snapshot = $snapshot ?? [
        'rolling_12_sales' => (int) $currentSalesSnapshot,
        'rolling_12_median_price' => (int) $currentMedianSnapshot,
        'rolling_12_price_yoy' => $previousMedianSnapshot > 0 ? (($currentMedianSnapshot - $previousMedianSnapshot) / $previousMedianSnapshot) * 100 : 0,
        'rolling_12_sales_yoy' => $previousSalesSnapshot > 0 ? (($currentSalesSnapshot - $previousSalesSnapshot) / $previousSalesSnapshot) * 100 : 0,
    ];
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs text-gray-500 uppercase">LAST 12 MONTHS SALES</div>
        <div class="text-2xl font-semibold">
            {{ number_format($snapshot['rolling_12_sales']) }}
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs text-gray-500 uppercase">Median Price</div>
        <div class="text-2xl font-semibold">
            £{{ number_format($snapshot['rolling_12_median_price']) }}
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs text-gray-500 uppercase">MEDIAN PRICE CHANGE</div>
        <div class="text-2xl font-semibold {{ $snapshot['rolling_12_price_yoy'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
            {{ number_format($snapshot['rolling_12_price_yoy'], 1) }}%
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs text-gray-500 uppercase">SALES VOLUME CHANGE</div>
        <div class="text-2xl font-semibold {{ $snapshot['rolling_12_sales_yoy'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
            {{ number_format($snapshot['rolling_12_sales_yoy'], 1) }}%
        </div>
    </div>
</div>


{{-- Monthly Sales — Last 24 Months (England) --}}
@php
    // Allow page to render even if controller hasn't provided these yet
    $sales24Labels = $sales24Labels ?? [];
    $sales24Data   = $sales24Data ?? [];
    $hasMonthly24  = !empty($sales24Labels) && !empty($sales24Data);
@endphp

<section class="mb-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">National Trend</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-900">Monthly sales</h2>
            <p class="mt-2 text-sm text-zinc-600">England and Wales transaction flow over the last 24 months.</p>
        </div>
        <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Last 24 months</span>
    </div>
    <p class="mt-4 text-xs text-zinc-500 italic">
        Note: Land Registry backfills the most recent months; the last few months may be incomplete. April 2025 is likely a rush ahead of year end.
    </p>

    <div class="mt-5 h-72 min-w-0 overflow-hidden sm:h-80">
        <canvas id="sales24Chart" class="block h-full w-full max-w-full"></canvas>
    </div>

    @unless($hasMonthly24)
        <p class="mt-3 text-sm text-zinc-500">
            Monthly data not loaded yet. Add <code>$sales24Labels</code> and <code>$sales24Data</code> in the controller to enable this chart.
        </p>
    @endunless
</section>

@push('scripts')
<script>
(function(){
  const labels = @json($sales24Labels ?? []);
  const data   = @json($sales24Data ?? []);

  if (!labels.length || !data.length) return;


  const ctx = document.getElementById('sales24Chart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Sales',
          data,
          type: 'bar',
          backgroundColor: 'rgba(37, 99, 235, 0.74)',
          borderColor: '#2563eb',
          borderWidth: 1,
          borderRadius: 8,
          maxBarThickness: 28
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(24, 24, 27, 0.94)',
          titleColor: '#fafafa',
          bodyColor: '#f4f4f5',
          borderColor: 'rgba(161, 161, 170, 0.35)',
          borderWidth: 1,
          padding: 12,
          callbacks: {
            label: (ctx) => {
              const v = ctx.parsed.y ?? 0;
              return new Intl.NumberFormat('en-GB').format(v) + ' sales';
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          border: { color: 'rgba(113, 113, 122, 0.22)' },
          ticks: {
            color: '#52525b',
            autoSkip: false,       // show every label
            maxRotation: 0,
            minRotation: 0,
            callback: function(value, index, ticks) {
              const lbl = this.getLabelForValue(value);
              const [month, year] = lbl.split(' ');
              const mMap = {
                Jan:'01', Feb:'02', Mar:'03', Apr:'04', May:'05', Jun:'06',
                Jul:'07', Aug:'08', Sep:'09', Oct:'10', Nov:'11', Dec:'12'
              };
              const shortYear = year ? year.slice(-2) : '';
              return (mMap[month] ?? month) + '/' + shortYear;
            },
            padding: 6
          }
        },
        y: {
          beginAtZero: true,
          grid: {
            color: 'rgba(113, 113, 122, 0.12)',
            drawBorder: false,
          },
          border: { color: 'rgba(113, 113, 122, 0.22)' },
          ticks: {
            color: '#52525b',
            callback: (v) => new Intl.NumberFormat('en-GB').format(v)
          }
        }
      }
    }
  });
})();
</script>
@endpush

<div class="max-w-7xl mx-auto grid grid-cols-1 gap-6 xl:grid-cols-2">
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Market Activity</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Rolling sales volume</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">12-month rolling</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="salesChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Prime Signals</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Largest recorded sale</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Top 3 on hover</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="topSaleChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Price Ladder</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Median, 90th percentile, and top 5% average</h2>
                <p class="mt-2 text-sm text-zinc-600">Median tracks the broader market while the top 5% average preserves higher-end tail activity.</p>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">England &amp; Wales</span>
        </div>
        <div class="mt-6 h-80 min-w-0 overflow-hidden sm:h-[26rem]">
            <canvas id="p90AvgTop5Chart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Housing Mix</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Property type split</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">12-month rolling</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="propertyTypeSplitChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Housing Mix</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Median price by property type</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">12-month rolling</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="avgPriceByTypeChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Stock Profile</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">New build vs existing</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Share of sales</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="newBuildSplitChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Tenure Mix</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Leasehold vs freehold</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Share of sales</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="durationSplitChart" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
</div>

<div class="max-w-7xl mx-auto mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Momentum</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Sales volume YoY change</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Rolling 12 month YoY</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="salesYoyBar" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Momentum</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Median price YoY change</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Rolling 12 month YoY</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="avgYoyBar" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Momentum</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">90th percentile YoY change</h2>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Rolling 12 month YoY</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="p90YoyBar" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Momentum</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-900">Top 5% average YoY change</h2>
                <p class="mt-2 text-sm text-zinc-600">Average is retained here to keep higher-end outlier moves visible.</p>
            </div>
            <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">Rolling 12 month YoY</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
            <canvas id="top5YoyBar" class="block h-full w-full max-w-full"></canvas>
        </div>
    </article>
</div>

@php
    $latestMonth = isset($latestMonth) ? \Carbon\Carbon::parse($latestMonth) : now()->startOfMonth();
    $rollingStart = isset($rollingStart) ? \Carbon\Carbon::parse($rollingStart) : $latestMonth->copy()->subMonths(11)->startOfMonth();
    $rollingEnd = isset($rollingEnd) ? \Carbon\Carbon::parse($rollingEnd) : $latestMonth->copy()->endOfMonth();
    $rollingLabel = 'Data range: Jan 1995 - '.$latestMonth->format('M Y').' (12-month rolling)';
    $rollingCachePrefix = 'property:home:rolling:'.$latestMonth->format('Ym');
    $annualRollingEndMonths = function () use ($latestMonth) {
        $earliestDate = DB::table('land_registry')->min('Date');
        if ($earliestDate === null) {
            return collect([$latestMonth->copy()]);
        }

        $earliestPossibleEnd = \Carbon\Carbon::parse($earliestDate)->startOfMonth()->addMonths(11);
        $firstEnd = $latestMonth->copy()->year($earliestPossibleEnd->year)->startOfMonth();

        if ($firstEnd->lt($earliestPossibleEnd)) {
            $firstEnd->addYear();
        }

        $endMonths = collect();
        $cursor = $firstEnd->copy();

        while ($cursor->lte($latestMonth)) {
            $endMonths->push($cursor->copy());
            $cursor->addYear();
        }

        return $endMonths->isNotEmpty() ? $endMonths : collect([$latestMonth->copy()]);
    };

    // England & Wales series alignment
    $ewYears = $avgPriceByYear->pluck('year');
    $ewP90Map = $ewP90->keyBy('year');
    $ewTop5Map = $ewTop5->keyBy('year');
    $ewP90Series = $ewYears->map(function($y) use ($ewP90Map){ return optional($ewP90Map->get($y))->p90_price; });
    $ewTop5Series = $ewYears->map(function($y) use ($ewTop5Map){ return optional($ewTop5Map->get($y))->top5_avg; });
    $ewTopSaleMap = $ewTopSalePerYear->keyBy('year');
    $ewTopSaleSeries = $ewYears->map(function($y) use ($ewTopSaleMap){ return optional($ewTopSaleMap->get($y))->top_sale; });

    // Build a lightweight map year -> [{rn, price, postcode, date}, ... up to 3] for tooltip
    $ewTop3ByYearArr = isset($ewTop3PerYear)
        ? $ewTop3PerYear->groupBy('year')->map(function($g){
            return $g->sortBy('rn')->map(function($row){
                return [
                    'rn' => (int) ($row->rn ?? 0),
                    'price' => (int) ($row->Price ?? 0),
                    'postcode' => $row->Postcode ?? null,
                    'date' => $row->Date ?? null,
                ];
            })->values();
        })
        : collect();

    // === Year-over-Year % change (aligned to $ewYears) ===
    $avgMap = $avgPriceByYear->keyBy('year');
    $avgSeries = $ewYears->map(fn($y) => optional($avgMap->get($y))->avg_price);

    $avgYoY = collect();
    $p90YoY = collect();
    $top5YoY = collect();
    for ($i = 0; $i < count($ewYears); $i++) {
        if ($i === 0) {
            $avgYoY->push(null);
            $p90YoY->push(null);
            $top5YoY->push(null);
            continue;
        }
        $prev = $i - 1;
        $prevAvg = (float) ($avgSeries[$prev] ?? 0);
        $currAvg = (float) ($avgSeries[$i] ?? 0);
        $prevP90 = (float) ($ewP90Series[$prev] ?? 0);
        $currP90 = (float) ($ewP90Series[$i] ?? 0);
        $prevTop5 = (float) ($ewTop5Series[$prev] ?? 0);
        $currTop5 = (float) ($ewTop5Series[$i] ?? 0);

        $avgYoY->push(($prevAvg > 0) ? (($currAvg - $prevAvg) / $prevAvg) * 100 : null);
        $p90YoY->push(($prevP90 > 0) ? (($currP90 - $prevP90) / $prevP90) * 100 : null);
        $top5YoY->push(($prevTop5 > 0) ? (($currTop5 - $prevTop5) / $prevTop5) * 100 : null);
    }

    $salesMap = $salesByYear->keyBy('year');
    $salesSeries = $ewYears->map(fn($y) => optional($salesMap->get($y))->total);
    $salesYoY = collect();
    for ($i = 0; $i < count($ewYears); $i++) {
        if ($i === 0) {
            $salesYoY->push(null);
            continue;
        }
        $prev = $i - 1;
        $prevSales = (float) ($salesSeries[$prev] ?? 0);
        $currSales = (float) ($salesSeries[$i] ?? 0);
        $salesYoY->push(($prevSales > 0) ? (($currSales - $prevSales) / $prevSales) * 100 : null);
    }

    // === Axis helpers to tighten top-row charts ===
    $salesMinVal = (int) collect($salesByYear->pluck('total'))->min();
    $topSaleMinVal = (int) collect($ewTopSaleSeries)->filter(fn($v) => !is_null($v))->min();
    $p90BlockMinVal = (int) collect([
        (int) collect($avgPriceByYear->pluck('avg_price'))->min(),
        (int) collect($ewP90Series)->filter(fn($v) => !is_null($v))->min(),
        (int) collect($ewTop5Series)->filter(fn($v) => !is_null($v))->min(),
    ])->min();

    $homeCacheTtl = now()->addDays(45);

    $typePayload = \Illuminate\Support\Facades\Cache::remember("{$rollingCachePrefix}:typeSplit", $homeCacheTtl, function () use ($annualRollingEndMonths) {
        $data = $annualRollingEndMonths()->flatMap(function ($endMonth) {
            $start = $endMonth->copy()->subMonths(11)->startOfMonth();
            $end = $endMonth->copy()->endOfMonth();

            return DB::table('land_registry')
                ->selectRaw('"PropertyType" as type, COUNT(*) as total')
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$start, $end])
                ->whereIn('PropertyType', ['D','S','T','F'])
                ->groupBy('PropertyType')
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $endMonth->year,
                    'type' => $row->type,
                    'total' => (int) $row->total,
                ]);
        })->values();

        return [
            'data' => $data,
        ];
    });
    $typeRows = collect($typePayload['data'] ?? []);

    $typeByYear = collect($typeRows)->groupBy('year')->map(function ($rows) {
        $out = ['D' => 0, 'S' => 0, 'T' => 0, 'F' => 0];
        foreach ($rows as $r) {
            $t = (string) ($r->type ?? '');
            if (isset($out[$t])) {
                $out[$t] = (int) ($r->total ?? 0);
            }
        }
        return $out;
    });

    $typeYears = $ewYears;
    $typeSeriesD = $typeYears->map(fn($y) => (int) (($typeByYear[$y]['D'] ?? 0)));
    $typeSeriesS = $typeYears->map(fn($y) => (int) (($typeByYear[$y]['S'] ?? 0)));
    $typeSeriesT = $typeYears->map(fn($y) => (int) (($typeByYear[$y]['T'] ?? 0)));
    $typeSeriesF = $typeYears->map(fn($y) => (int) (($typeByYear[$y]['F'] ?? 0)));

    $avgTypePayload = \Illuminate\Support\Facades\Cache::remember("{$rollingCachePrefix}:avgPriceByType", $homeCacheTtl, function () use ($annualRollingEndMonths) {
        $medianExpr = DB::connection()->getDriverName() === 'pgsql'
            ? 'PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY "Price")'
            : 'AVG("Price")';

        $data = $annualRollingEndMonths()->flatMap(function ($endMonth) use ($medianExpr) {
            $start = $endMonth->copy()->subMonths(11)->startOfMonth();
            $end = $endMonth->copy()->endOfMonth();

            return DB::table('land_registry')
                ->selectRaw("\"PropertyType\" as type, ROUND({$medianExpr}) as avg_price")
                ->where('PPDCategoryType', 'A')
                ->whereBetween('Date', [$start, $end])
                ->whereIn('PropertyType', ['D','S','T','F'])
                ->whereNotNull('Price')
                ->where('Price', '>', 0)
                ->groupBy('PropertyType')
                ->get()
                ->map(fn ($row) => (object) [
                    'year' => $endMonth->year,
                    'type' => $row->type,
                    'avg_price' => is_null($row->avg_price) ? null : (int) $row->avg_price,
                ]);
        })->values();

        return [
            'data' => $data,
        ];
    });
    $avgTypeRows = collect($avgTypePayload['data'] ?? []);

    $avgTypeByYear = collect($avgTypeRows)->groupBy('year')->map(function ($rows) {
        $out = ['D' => null, 'S' => null, 'T' => null, 'F' => null];
        foreach ($rows as $r) {
            $t = (string) ($r->type ?? '');
            if (array_key_exists($t, $out)) {
                $out[$t] = is_null($r->avg_price) ? null : (int) $r->avg_price;
            }
        }
        return $out;
    });

    $avgTypeYears = $ewYears;
    $avgTypeSeriesD = $avgTypeYears->map(fn($y) => $avgTypeByYear[$y]['D'] ?? null);
    $avgTypeSeriesS = $avgTypeYears->map(fn($y) => $avgTypeByYear[$y]['S'] ?? null);
    $avgTypeSeriesT = $avgTypeYears->map(fn($y) => $avgTypeByYear[$y]['T'] ?? null);
    $avgTypeSeriesF = $avgTypeYears->map(fn($y) => $avgTypeByYear[$y]['F'] ?? null);

    $nbPayload = \Illuminate\Support\Facades\Cache::remember("{$rollingCachePrefix}:newBuildSplit", $homeCacheTtl, function () use ($annualRollingEndMonths) {
        return [
            'data' => $annualRollingEndMonths()->flatMap(function ($endMonth) {
                $start = $endMonth->copy()->subMonths(11)->startOfMonth();
                $end = $endMonth->copy()->endOfMonth();

                return DB::table('land_registry')
                    ->selectRaw('"NewBuild" as nb, COUNT(*) as total')
                    ->where('PPDCategoryType', 'A')
                    ->whereBetween('Date', [$start, $end])
                    ->whereIn('NewBuild', ['Y','N'])
                    ->groupBy('NewBuild')
                    ->get()
                    ->map(fn ($row) => (object) [
                        'year' => $endMonth->year,
                        'nb' => $row->nb,
                        'total' => (int) $row->total,
                    ]);
            })->values(),
        ];
    });
    $nbRows = collect($nbPayload['data'] ?? $nbPayload);

    $nbByYear = collect($nbRows)->groupBy('year')->map(function ($rows) {
        $out = ['Y' => 0, 'N' => 0];
        foreach ($rows as $r) {
            $k = (string) ($r->nb ?? '');
            if (isset($out[$k])) {
                $out[$k] = (int) ($r->total ?? 0);
            }
        }
        return $out;
    });

    $nbYears = $ewYears;
    $nbPctNew = $nbYears->map(function ($y) use ($nbByYear) {
        $yKey = (string) $y;
        $new = (int) (($nbByYear[$yKey]['Y'] ?? 0));
        $old = (int) (($nbByYear[$yKey]['N'] ?? 0));
        $tot = $new + $old;
        return $tot > 0 ? round(($new / $tot) * 100, 2) : 0;
    });
    $nbPctExisting = $nbYears->map(function ($y) use ($nbByYear) {
        $yKey = (string) $y;
        $new = (int) (($nbByYear[$yKey]['Y'] ?? 0));
        $old = (int) (($nbByYear[$yKey]['N'] ?? 0));
        $tot = $new + $old;
        return $tot > 0 ? round(($old / $tot) * 100, 2) : 0;
    });

$durPayload = \Illuminate\Support\Facades\Cache::remember("{$rollingCachePrefix}:durationSplit", $homeCacheTtl, function () use ($annualRollingEndMonths) {
    $data = $annualRollingEndMonths()->flatMap(function ($endMonth) {
        $start = $endMonth->copy()->subMonths(11)->startOfMonth();
        $end = $endMonth->copy()->endOfMonth();

        return DB::table('land_registry')
            ->selectRaw('"Duration" as dur, COUNT(*) as total')
            ->where('PPDCategoryType', 'A')
            ->whereBetween('Date', [$start, $end])
            ->whereIn('Duration', ['F','L'])
            ->groupBy('Duration')
            ->get()
            ->map(fn ($row) => (object) [
                'year' => $endMonth->year,
                'dur' => $row->dur,
                'total' => (int) $row->total,
            ]);
    })->values();

    return [
        'data' => $data,
    ];
});
$durRows = collect($durPayload['data'] ?? []);

$durByYear = collect($durRows)->groupBy('year')->map(function ($rows) {
    $out = ['F' => 0, 'L' => 0];
    foreach ($rows as $r) {
        $k = (string) ($r->dur ?? '');
        if (isset($out[$k])) {
            $out[$k] = (int) ($r->total ?? 0);
        }
    }
    return $out;
});

$durYears = $ewYears;

$durPctFreehold = $durYears->map(function ($y) use ($durByYear) {
    $yKey = (string) $y;
    $f = (int) (($durByYear[$yKey]['F'] ?? 0));
    $l = (int) (($durByYear[$yKey]['L'] ?? 0));
    $tot = $f + $l;
    return $tot > 0 ? round(($f / $tot) * 100, 2) : 0;
});

$durPctLeasehold = $durYears->map(function ($y) use ($durByYear) {
    $yKey = (string) $y;
    $f = (int) (($durByYear[$yKey]['F'] ?? 0));
    $l = (int) (($durByYear[$yKey]['L'] ?? 0));
    $tot = $f + $l;
    return $tot > 0 ? round(($l / $tot) * 100, 2) : 0;
});

@endphp

<script>
    // === Axis minimums for tighter y-axes ===
    const salesMinVal   = {!! json_encode($salesMinVal) !!};
    const topSaleMinVal = {!! json_encode($topSaleMinVal) !!};
    const p90BlockMinVal = {!! json_encode($p90BlockMinVal) !!};
    const rollingPeriodTitle = {!! json_encode($rollingLabel) !!};
    const isSinglePeriod = {!! json_encode($salesByYear->count() <= 1) !!};

    function buildYearLabels(labels) {
        return labels.map((label) => String(label).replace(/,/g, ''));
    }

    function buildYearRangeTitle() {
        return rollingPeriodTitle;
    }

    function buildYoYYearRangeTitle(labels) {
        const years = buildYearLabels(labels);

        if (years.length <= 1) {
            return `Rolling 12 month YoY ending ${new Date({!! json_encode($latestMonth->toDateString()) !!}).toLocaleString('en-GB', { month: 'short', year: 'numeric' })}`;
        }

        const firstComparableYear = years[1];
        const lastYear = years[years.length - 1];

        return firstComparableYear === lastYear
            ? `Rolling 12 month YoY: ${firstComparableYear}`
            : `Rolling 12 month YoY: ${firstComparableYear} to ${lastYear}`;
    }

    function buildCategoryYearTickCallback(labels) {
        const years = buildYearLabels(labels);

        return function(value, index) {
            const label = years[index] ?? String(this.getLabelForValue(value)).replace(/,/g, '');

            return label;
        };
    }

    function buildLinearYearTickCallback(years) {
        const cleanYears = buildYearLabels(years).map((year) => Number(year));
        const yearIndexMap = new Map(cleanYears.map((year, index) => [year, index]));

        return function(value) {
            const numericValue = Number(value);
            const index = yearIndexMap.get(numericValue);

            if (typeof index === 'undefined') {
                return '';
            }

            return String(cleanYears[index]);
        };
    }

    const chartGridColor = 'rgba(113, 113, 122, 0.12)';
    const chartBorderColor = 'rgba(113, 113, 122, 0.22)';
    const chartTickColor = '#52525b';
    const chartTitleColor = '#71717a';
    const chartLegendColor = '#3f3f46';

    function buildCommonChartOptions(overrides = {}) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
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
                    displayColors: true,
                },
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false,
                    },
                    border: {
                        color: chartBorderColor,
                    },
                    ticks: {
                        color: chartTickColor,
                    },
                    title: {
                        color: chartTitleColor,
                        font: {
                            size: 11,
                            weight: '600',
                        },
                    },
                },
                y: {
                    grid: {
                        color: chartGridColor,
                        drawBorder: false,
                    },
                    border: {
                        color: chartBorderColor,
                    },
                    ticks: {
                        color: chartTickColor,
                    },
                    title: {
                        color: chartTitleColor,
                        font: {
                            size: 11,
                            weight: '600',
                        },
                    },
                },
            },
            ...overrides,
        };
    }

    const ctx = document.getElementById('salesChart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode($salesByYear->pluck('year')) !!},
            datasets: [{
                label: 'Sales across England & Wales',
                data: {!! json_encode($salesByYear->pluck('total')) !!},
                borderColor: '#65a30d',
                backgroundColor: 'rgba(101, 163, 13, 0.14)',
                fill: true,
                tension: 0.28,
                showLine: !isSinglePeriod,
                pointBackgroundColor: function(ctx) {
                    const index = ctx.dataIndex;
                    const data = ctx.dataset.data;
                    if (index === 0) return '#65a30d';
                    return data[index] < data[index-1] ? '#dc2626' : '#65a30d';
                },
                pointRadius: isSinglePeriod ? 8 : 3,
                pointHoverRadius: isSinglePeriod ? 10 : 5,
                pointBorderWidth: 0,
            }]
        },
        options: buildCommonChartOptions({
            scales: {
                x: {
                    offset: false,
                    title: {
                        display: true,
                        text: rollingPeriodTitle
                    },
                    ticks: {
                        display: false,
                        autoSkip: false
                    }
                },
                y: {
                    beginAtZero: false,
                    suggestedMin: Math.max(0, salesMinVal * 0.9),
                    ticks: {
                        callback: (value) => new Intl.NumberFormat('en-GB').format(value),
                    },
                }
            }
        })
    });
</script>

<script>
    // Map of year -> [{rn, price, postcode, date}, ...]
    const ewTop3ByYear = {!! json_encode($ewTop3ByYearArr, JSON_UNESCAPED_UNICODE) !!};
</script>

<script>
    const ctxTopSale = document.getElementById('topSaleChart').getContext('2d');

    // Build scatter points with explicit {x: year, y: top_sale}
    const scatterYears = {!! json_encode($avgPriceByYear->pluck('year')) !!}.map(s => parseInt(String(s).replace(/,/g,''), 10));
    const scatterYvals = {!! json_encode($ewTopSaleSeries) !!};
    const scatterData = scatterYears.map((y, i) => ({ x: y, y: scatterYvals[i] }));

    new Chart(ctxTopSale, {
        type: 'scatter',
        data: {
            // labels not needed when using point objects with linear x-scale
            datasets: [
                {
                    label: 'Largest Sale (hover over points to see top 3)',
                    data: scatterData,
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15, 118, 110, 0.82)',
                    showLine: false,
                    pointRadius: isSinglePeriod ? 8 : 4,
                    pointHoverRadius: isSinglePeriod ? 10 : 6,
                    pointStyle: 'circle'
                }
            ]
        },
        options: buildCommonChartOptions({
            plugins: {
                legend: {
                    position: 'top',
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
                        // Show the year as the primary label
                        label: function(context) {
                            return rollingPeriodTitle;
                        },
                        afterBody: function(items) {
                            if (!items.length) return [];
                            const year = String(items[0].parsed.x);
                            const rows = ewTop3ByYear[year] || [];
                            const nf = new Intl.NumberFormat('en-GB');
                            if (!rows.length) return ['No data for top 3.'];
                            return rows.map((r, i) => {
                                const price = '£' + nf.format(r.price || 0);
                                const pc = r.postcode ? ` – ${r.postcode}` : '';
                                const dt = r.date ? (() => {
                                    const raw = String(r.date).split(' ')[0];
                                    const parts = raw.includes('-') ? raw.split('-') : raw.split('/');
                                    if (parts.length === 3) {
                                        // assume incoming is YYYY-MM-DD or YYYY/MM/DD
                                        const [yyyy, mm, dd] = parts;
                                        return ` (${dd}/${mm}/${yyyy})`;
                                    }
                                    return ` (${raw})`;
                                })() : '';
                                return `Top ${i+1}: ${price}${pc}${dt}`;
                            });
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'linear',
                    min: isSinglePeriod ? scatterYears[0] - 1 : Math.min(...scatterYears),
                    max: isSinglePeriod ? scatterYears[0] + 1 : Math.max(...scatterYears),
                    offset: false,
                    title: {
                        display: true,
                        text: rollingPeriodTitle
                    },
                    ticks: {
                        display: false,
                        stepSize: 1,
                        callback: buildLinearYearTickCallback({!! json_encode($avgPriceByYear->pluck('year')) !!}),
                        autoSkip: false,
                        precision: 0
                    }
                },
                y: {
                    beginAtZero: false,
                    suggestedMin: Math.max(0, topSaleMinVal * 0.9),
                    ticks: {
                        callback: (value) => '£' + new Intl.NumberFormat('en-GB', { notation: 'compact', maximumFractionDigits: 1 }).format(value),
                    },
                }
            }
        })
    });
</script>

<script>
    const ctxP90AvgTop5 = document.getElementById('p90AvgTop5Chart').getContext('2d');
    new Chart(ctxP90AvgTop5, {
        type: 'line',
        data: {
            labels: {!! json_encode($avgPriceByYear->pluck('year')) !!},
            datasets: [
                {
                    label: 'Median Sale Price',
                    data: {!! json_encode($avgPriceByYear->pluck('avg_price')) !!},
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    fill: true,
                    tension: 0.28,
                    showLine: !isSinglePeriod,
                    pointRadius: isSinglePeriod ? 8 : 3,
                    pointHoverRadius: isSinglePeriod ? 10 : 4
                },
                {
                    label: '90th Percentile',
                    data: {!! json_encode($ewP90Series) !!},
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15, 118, 110, 0.1)',
                    borderDash: [6,1],
                    fill: true,
                    tension: 0.28,
                    showLine: !isSinglePeriod,
                    pointRadius: isSinglePeriod ? 8 : 4,
                    pointHoverRadius: isSinglePeriod ? 10 : 5
                },
                {
                    label: 'Top 5% Average',
                    data: {!! json_encode($ewTop5Series) !!},
                    borderColor: '#ea580c',
                    backgroundColor: 'rgba(234, 88, 12, 0.1)',
                    fill: true,
                    tension: 0.28,
                    showLine: !isSinglePeriod,
                    pointRadius: isSinglePeriod ? 8 : 3,
                    pointHoverRadius: isSinglePeriod ? 10 : 5
                }
            ]
        },
        options: buildCommonChartOptions({
            plugins: {
                legend: {
                    position: 'top',
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
                        label: function(context) {
                            const value = context.parsed.y;
                            if (value === null || typeof value === 'undefined') {
                                return `${context.dataset.label}: n/a`;
                            }

                            return `${context.dataset.label}: £${new Intl.NumberFormat('en-GB').format(value)}`;
                        }
                    }
                }
            },
            scales: { 
                x: { 
                    offset: false,
                    title: {
                        display: true,
                        text: rollingPeriodTitle
                    },
                    ticks: { 
                        display: false,
                        autoSkip: false
                    } 
                }, 
                y: {
                    beginAtZero: false,
                    suggestedMin: Math.max(0, p90BlockMinVal * 0.9),
                    ticks: {
                        callback: (value) => '£' + new Intl.NumberFormat('en-GB', { notation: 'compact', maximumFractionDigits: 1 }).format(value),
                    },
                }
            }
        })
    });
</script>

<script>
    const labelsYoY = buildYearLabels({!! json_encode($ewYears) !!});
    const labelsYoYTitle = buildYoYYearRangeTitle(labelsYoY);

    function barColorsFrom(values) {
        return values.map(v => {
            if (v === null || typeof v === 'undefined') return 'rgba(150,150,150,0.6)';
            return v >= 0 ? 'rgba(87,161,0,0.7)' : 'rgba(239,68,68,0.7)'; // green for up, red for down
        });
    }

    function borderColorsFrom(values) {
        return values.map(v => {
            if (v === null || typeof v === 'undefined') return 'rgba(150,150,150,1)';
            return v >= 0 ? 'rgba(87,161,0,1)' : 'rgba(239,68,68,1)';
        });
    }

    function makeYoyBar(canvasId, series, label) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labelsYoY,
                datasets: [{
                    label: label,
                    data: series,
                    backgroundColor: barColorsFrom(series),
                    borderColor: borderColorsFrom(series),
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 28,
                }]
            },
            options: buildCommonChartOptions({
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const v = context.parsed.y;
                                if (v === null || typeof v === 'undefined') return 'No prior year';
                                const sign = v >= 0 ? '+' : '';
                                return `${sign}${v.toFixed(2)}%`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: labelsYoYTitle
                        },
                        ticks: {
                            display: false,
                            autoSkip: false
                        }
                    },
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) { return value + '%'; }
                        },
                        grid: { drawBorder: false }
                    }
                }
            })
        });
    }

    const avgSeriesYoY  = {!! json_encode($avgYoY->map(fn($v) => is_null($v) ? null : round($v, 2))) !!};
    const p90SeriesYoY  = {!! json_encode($p90YoY->map(fn($v) => is_null($v) ? null : round($v, 2))) !!};
    const top5SeriesYoY = {!! json_encode($top5YoY->map(fn($v) => is_null($v) ? null : round($v, 2))) !!};
    const salesSeriesYoY = {!! json_encode($salesYoY->map(fn($v) => is_null($v) ? null : round($v, 2))) !!};

    // Build charts
    (function(){
        // Average
        makeYoyBar('avgYoyBar', avgSeriesYoY, 'Median Price YoY %');
        // Patch dataset with the right series inside the function-created chart
        // 90th Percentile
        const ctxP90 = document.getElementById('p90YoyBar').getContext('2d');
        new Chart(ctxP90, {
            type: 'bar',
            data: {
                labels: labelsYoY,
                datasets: [{
                    data: p90SeriesYoY,
                    backgroundColor: barColorsFrom(p90SeriesYoY),
                    borderColor: borderColorsFrom(p90SeriesYoY),
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 28,
                }]
            },
            options: buildCommonChartOptions({
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c){ const v=c.parsed.y; if (v==null) return 'No prior year'; const s=v>=0?'+':''; return `${s}${v.toFixed(2)}%`; } } } },
                scales: {
                    x: { 
                        title: {
                            display: true,
                            text: labelsYoYTitle
                        },
                        ticks: { 
                            display: false, autoSkip: false 
                        } 
                    },
                    y: { beginAtZero: false, ticks: { callback: v => v + '%' } }
                }
            })
        });

        // Top 5%
        const ctxTop5 = document.getElementById('top5YoyBar').getContext('2d');
        new Chart(ctxTop5, {
            type: 'bar',
            data: {
                labels: labelsYoY,
                datasets: [{
                    data: top5SeriesYoY,
                    backgroundColor: barColorsFrom(top5SeriesYoY),
                    borderColor: borderColorsFrom(top5SeriesYoY),
                    borderWidth: 1,
                    borderRadius: 8,
                    maxBarThickness: 28,
                }]
            },
            options: buildCommonChartOptions({
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c){ const v=c.parsed.y; if (v==null) return 'No prior year'; const s=v>=0?'+':''; return `${s}${v.toFixed(2)}%`; } } } },
                scales: {
                    x: { 
                        title: {
                            display: true,
                            text: labelsYoYTitle
                        },
                        ticks: { 
                            display: false, autoSkip: false 
                        } 
                    },
                    y: { beginAtZero: false, ticks: { callback: v => v + '%' } }
                }
            })
        });

        // Sales Volume
        makeYoyBar('salesYoyBar', salesSeriesYoY, 'Sales Volume YoY %');
    })();
</script>

<script>
    (function(){
        const labels = buildYearLabels({!! json_encode($typeYears ?? collect()) !!});
        if (!labels.length) return;
        const labelTitle = buildYearRangeTitle(labels);

        const dataD = {!! json_encode($typeSeriesD ?? collect()) !!};
        const dataS = {!! json_encode($typeSeriesS ?? collect()) !!};
        const dataT = {!! json_encode($typeSeriesT ?? collect()) !!};
        const dataF = {!! json_encode($typeSeriesF ?? collect()) !!};

        const el = document.getElementById('propertyTypeSplitChart');
        if (!el) return;

        const ctx = el.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Detached',      data: dataD, backgroundColor: 'rgba(37, 99, 235, 0.72)', borderColor: '#2563eb', borderWidth: 1, borderRadius: 8, stack: 'types' },
                    { label: 'Semi-detached', data: dataS, backgroundColor: 'rgba(15, 118, 110, 0.72)', borderColor: '#0f766e', borderWidth: 1, borderRadius: 8, stack: 'types' },
                    { label: 'Terraced',      data: dataT, backgroundColor: 'rgba(234, 88, 12, 0.72)', borderColor: '#ea580c', borderWidth: 1, borderRadius: 8, stack: 'types' },
                    { label: 'Flat',          data: dataF, backgroundColor: 'rgba(225, 29, 72, 0.72)', borderColor: '#e11d48', borderWidth: 1, borderRadius: 8, stack: 'types' },
                ]
            },
            options: buildCommonChartOptions({
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10,
                            color: chartLegendColor,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c){
                                const v = c.parsed.y ?? 0;
                                return `${c.dataset.label}: ` + new Intl.NumberFormat('en-GB').format(v);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: labelTitle
                        },
                        ticks: {
                            display: false,
                            autoSkip: false
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => new Intl.NumberFormat('en-GB').format(v)
                        }
                    }
                }
            })
        });
    })();
</script>

<script>
    (function(){
        const labels = buildYearLabels({!! json_encode($avgTypeYears ?? collect()) !!});
        if (!labels.length) return;
        const labelTitle = buildYearRangeTitle(labels);

        const d = {!! json_encode($avgTypeSeriesD ?? collect()) !!};
        const s = {!! json_encode($avgTypeSeriesS ?? collect()) !!};
        const t = {!! json_encode($avgTypeSeriesT ?? collect()) !!};
        const f = {!! json_encode($avgTypeSeriesF ?? collect()) !!};

        const el = document.getElementById('avgPriceByTypeChart');
        if (!el) return;

        const ctx = el.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Detached',      data: d, borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.12)', fill: true, tension: 0.28, pointRadius: 3, pointHoverRadius: 5 },
                    { label: 'Semi-detached', data: s, borderColor: '#0f766e', backgroundColor: 'rgba(15, 118, 110, 0.1)', fill: true, tension: 0.28, pointRadius: 3, pointHoverRadius: 5 },
                    { label: 'Terraced',      data: t, borderColor: '#ea580c', backgroundColor: 'rgba(234, 88, 12, 0.1)', fill: true, tension: 0.28, pointRadius: 3, pointHoverRadius: 5 },
                    { label: 'Flat',          data: f, borderColor: '#e11d48', backgroundColor: 'rgba(225, 29, 72, 0.1)', fill: true, tension: 0.28, pointRadius: 3, pointHoverRadius: 5 },
                ]
            },
            options: buildCommonChartOptions({
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10,
                            color: chartLegendColor,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c){
                                const v = c.parsed.y;
                                if (v === null || typeof v === 'undefined') return `${c.dataset.label}: n/a`;
                                return `${c.dataset.label}: £` + new Intl.NumberFormat('en-GB').format(v);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: labelTitle
                        },
                        ticks: {
                            display: false,
                            autoSkip: false
                        }
                    },
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: (v) => '£' + new Intl.NumberFormat('en-GB').format(v)
                        }
                    }
                }
            })
        });
    })();
</script>

</div>

<script>
    (function(){
        const labels = buildYearLabels({!! json_encode($nbYears ?? collect()) !!});
        if (!labels.length) return;
        const labelTitle = buildYearRangeTitle(labels);

        const pctNew = {!! json_encode($nbPctNew ?? collect()) !!};
        const pctOld = {!! json_encode($nbPctExisting ?? collect()) !!};

        const el = document.getElementById('newBuildSplitChart');
        if (!el) return;

        const ctx = el.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'New build (Y)', data: pctNew, backgroundColor: 'rgba(101, 163, 13, 0.72)', borderColor: '#65a30d', borderWidth: 1, borderRadius: 8, stack: 'nb' },
                    { label: 'Existing (N)',  data: pctOld, backgroundColor: 'rgba(37, 99, 235, 0.72)', borderColor: '#2563eb', borderWidth: 1, borderRadius: 8, stack: 'nb' },
                ]
            },
            options: buildCommonChartOptions({
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10,
                            color: chartLegendColor,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c){
                                const v = c.parsed.y ?? 0;
                                return `${c.dataset.label}: ${Number(v).toFixed(2)}%`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: labelTitle
                        },
                        ticks: {
                            display: false,
                            autoSkip: false
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: (v) => v + '%'
                        }
                    }
                }
            })
        });
    })();
</script>

<script>
    (function(){
        const labels = buildYearLabels({!! json_encode($durYears ?? collect()) !!});
        if (!labels.length) return;
        const labelTitle = buildYearRangeTitle(labels);

        const pctF = {!! json_encode($durPctFreehold ?? collect()) !!};
        const pctL = {!! json_encode($durPctLeasehold ?? collect()) !!};

        const el = document.getElementById('durationSplitChart');
        if (!el) return;

        const ctx = el.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Leasehold (L)', data: pctL, backgroundColor: 'rgba(234, 88, 12, 0.72)', borderColor: '#ea580c', borderWidth: 1, borderRadius: 8, stack: 'dur', order: 0 },
                    { label: 'Freehold (F)',  data: pctF, backgroundColor: 'rgba(101, 163, 13, 0.72)', borderColor: '#65a30d', borderWidth: 1, borderRadius: 8, stack: 'dur', order: 1 },
                ]
            },
            options: buildCommonChartOptions({
                plugins: {
                    legend: {
                        position: 'top',
                        reverse: true,
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10,
                            color: chartLegendColor,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c){
                                const v = c.parsed.y ?? 0;
                                return `${c.dataset.label}: ${Number(v).toFixed(2)}%`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: labelTitle
                        },
                        ticks: {
                            display: false,
                            autoSkip: false
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: (v) => v + '%' }
                    }
                }
            })
        });
    })();
</script>

@endsection
