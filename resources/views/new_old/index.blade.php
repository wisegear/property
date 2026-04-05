@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">

  {{-- Hero / summary card --}}
  <section class="relative z-0 mb-6 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm md:flex md:flex-row md:items-center md:justify-between md:p-8">
      @include('partials.hero-background')
    <div class="relative z-10 max-w-4xl">
      <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
        <span class="h-2 w-2 rounded-full bg-lime-500"></span>
        New Build Comparison
      </div>
      <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-900 md:text-3xl">New Build vs Existing Sales Dashboard</h1>
      <p class="mt-4 text-sm leading-6 text-zinc-600">
        This dashboard compares <span class="font-semibold">new build</span> and <span class="font-semibold">existing property</span> sales across the UK.  This data is provided as part of
        the Government's HPI data which may differ from the England/Wales Land Registry information used elsewhere on this site.
      </p>
    </div>
    <div class="relative z-10 mt-6 flex-shrink-0 md:mt-0 md:ml-8">
      <img src="{{ asset('assets/images/site/new_old.jpg') }}" alt="New vs Existing" class="w-64 h-auto">
    </div>
  </section>

  {{-- UK trend --}}
  <div class="mb-6">
    <h2 class="mb-3 text-xl font-semibold text-zinc-900">Last 15 years — UK totals</h2>
    <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">National Trend</p>
          <h3 class="mt-2 text-xl font-semibold text-zinc-900">United Kingdom</h3>
        </div>
        <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">15 years</span>
      </div>
      <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
        <canvas id="trendChart" class="block h-full w-full max-w-full"></canvas>
      </div>
    </article>
  </div>

  {{-- Nation trends --}}
  <div class="mb-6">
    <h2 class="mb-3 text-xl font-semibold text-zinc-900">Last 15 years — by nation</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Nation Trend</p>
            <h3 class="mt-2 text-xl font-semibold text-zinc-900">England</h3>
          </div>
          <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">15 years</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
          <canvas id="trendChartEngland" class="block h-full w-full max-w-full"></canvas>
        </div>
      </article>

      <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Nation Trend</p>
            <h3 class="mt-2 text-xl font-semibold text-zinc-900">Scotland</h3>
          </div>
          <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">15 years</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
          <canvas id="trendChartScotland" class="block h-full w-full max-w-full"></canvas>
        </div>
      </article>

      <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Nation Trend</p>
            <h3 class="mt-2 text-xl font-semibold text-zinc-900">Wales</h3>
          </div>
          <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">15 years</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
          <canvas id="trendChartWales" class="block h-full w-full max-w-full"></canvas>
        </div>
      </article>

      <article class="min-w-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Nation Trend</p>
            <h3 class="mt-2 text-xl font-semibold text-zinc-900">Northern Ireland</h3>
          </div>
          <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-600">15 years</span>
        </div>
        <div class="mt-6 h-72 min-w-0 overflow-hidden sm:h-80">
          <canvas id="trendChartNorthernIreland" class="block h-full w-full max-w-full"></canvas>
        </div>
      </article>
    </div>
  </div>

  @push('scripts')
  <script>
    (function(){
      const trendData = @json($trend ?? []);
      const labels = trendData.map(d => d.date);
      const newData = trendData.map(d => d.new_vol);
      const oldData = trendData.map(d => d.old_vol);
      const nationTrends = @json($nation_trends ?? []);
      const chartGridColor = 'rgba(113, 113, 122, 0.12)';
      const chartBorderColor = 'rgba(113, 113, 122, 0.22)';
      const chartTickColor = '#52525b';
      const chartLegendColor = '#3f3f46';

      function renderLineChart(canvasId, labels, newData, oldData){
        const ctx = document.getElementById(canvasId);
        if(!ctx) return;

        // If a chart instance already exists (hot reload/navigation), destroy it
        if (ctx._chartInstance) { ctx._chartInstance.destroy(); }

        if (!labels.length) {
          const wrap = ctx.closest('.border');
          if (wrap) {
            wrap.innerHTML = '<div class="p-6 text-sm text-zinc-600">No trend data available for the selected date range.</div>';
          }
          return;
        }

        const formatNumber = (value) => new Intl.NumberFormat('en-GB').format(value);
        const barFill = 'rgba(101, 163, 13, 0.72)';
        const barBorder = '#65a30d';
        const lineColor = '#2563eb';
        const lineFill = 'rgba(37, 99, 235, 0.12)';

        const chart = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [
              {
                label: 'New build',
                type: 'bar',
                data: newData,
                backgroundColor: barFill,
                borderColor: barBorder,
                borderWidth: 1,
                borderRadius: 8,
                maxBarThickness: 28
              },
              {
                label: 'Existing',
                type: 'line',
                data: oldData,
                borderColor: lineColor,
                backgroundColor: lineFill,
                tension: 0.28,
                fill: true,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: function(ctx) {
                  const index = ctx.dataIndex;
                  const data = ctx.dataset.data;
                  if (index === 0) return lineColor;
                  return data[index] < data[index - 1] ? 'red' : lineColor;
                }
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: {
                position: 'top',
                labels: {
                  usePointStyle: true,
                  boxWidth: 10,
                  boxHeight: 10,
                  color: chartLegendColor
                }
              },
              tooltip: {
                backgroundColor: 'rgba(24, 24, 27, 0.94)',
                titleColor: '#fafafa',
                bodyColor: '#f4f4f5',
                borderColor: 'rgba(161, 161, 170, 0.35)',
                borderWidth: 1,
                padding: 12,
                callbacks: {
                  label: (ctx) => `${ctx.dataset.label}: ${formatNumber(ctx.parsed.y)}`
                }
              }
            },
            scales: {
              x: {
                grid: { display: false },
                border: { color: chartBorderColor },
                ticks: {
                  color: chartTickColor,
                  callback: function(value, index) {
                    const lbl = this.getLabelForValue(value);
                    const clean = String(lbl).replace(/,/g, '');
                    return (index % 2 === 0) ? clean : '';
                  },
                  padding: 12,
                  maxRotation: 0,
                  minRotation: 0,
                  autoSkip: false
                }
              },
              y: {
                beginAtZero: true,
                grid: { color: chartGridColor, drawBorder: false },
                border: { color: chartBorderColor },
                ticks: {
                  color: chartTickColor,
                  precision: 0,
                  callback: (value) => formatNumber(value)
                }
              }
            }
          }
        });

        ctx._chartInstance = chart;
      }

      function renderAllTrendCharts(){
        // UK
        renderLineChart('trendChart', labels, newData, oldData);

        // Nations
        const nations = [
          { key: 'England', id: 'trendChartEngland' },
          { key: 'Scotland', id: 'trendChartScotland' },
          { key: 'Wales', id: 'trendChartWales' },
          { key: 'Northern Ireland', id: 'trendChartNorthernIreland' },
        ];

        nations.forEach(n => {
          const rows = (nationTrends && nationTrends[n.key]) ? nationTrends[n.key] : [];
          const nLabels = rows.map(d => d.date);
          const nNew = rows.map(d => d.new_vol);
          const nOld = rows.map(d => d.old_vol);
          renderLineChart(n.id, nLabels, nNew, nOld);
        });
      }

      if (window.Chart) {
        renderAllTrendCharts();
      } else {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js';
        s.onload = renderAllTrendCharts;
        document.head.appendChild(s);
      }
    })();
  </script>
  @endpush

</div>
@endsection
