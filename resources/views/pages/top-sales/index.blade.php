@extends('layouts.app')
@include('partials.chartjs-head')

@section('title', $month->format('F Y').' High Value Property Sales | Property Research')
@section('description', 'Explore the highest-value residential property transactions in England and Wales for '.$month->format('F Y').', including the monthly 90th percentile benchmark, top sales, hotspots, property types and £1m+ market activity.')

@push('head')
<link rel="canonical" href="{{ $canonicalUrl }}">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
@endpush

@section('content')
@php
    $money = fn (?int $value): string => $value === null ? '—' : '£'.number_format($value);
    $compactMoney = fn (int $value): string => match (true) {
        $value >= 1000000000 => '£'.number_format($value / 1000000000, 1).'bn',
        $value >= 1000000 => '£'.number_format($value / 1000000, 1).'m',
        default => '£'.number_format($value),
    };
    $change = fn (?float $value): string => $value === null ? '—' : ($value > 0 ? '+' : '').number_format($value, 1).'%';
    $typeNames = ['D' => 'Detached', 'S' => 'Semi-detached', 'T' => 'Terraced', 'F' => 'Flat / maisonette', 'O' => 'Other'];
    $tenureNames = ['F' => 'Freehold', 'L' => 'Leasehold'];
    $maxType = max(array_column($propertyTypes, 'count') ?: [0]);
@endphp

<section class="relative -mx-6 -mt-6 overflow-hidden bg-white py-8 shadow-[0_10px_24px_-20px_rgba(15,23,42,0.18)] md:py-10">
    <div class="mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[minmax(0,1fr)_minmax(260px,0.36fr)]">
        <div>
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500"><span class="size-2 rounded-full bg-lime-600"></span>England and Wales · Category A sales</p>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-950 md:text-4xl">High Value Property — {{ $month->format('F Y') }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600">Exploring the top 10% of residential property transactions recorded by HM Land Registry.</p>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-500"><span class="font-semibold text-amber-700">Provisional:</span> Land Registry backfills recent months, so this dashboard recalculates as new records arrive.</p>
            @if (count($availableMonths))
                <nav class="mt-5 flex flex-wrap items-center gap-x-1 gap-y-2 border-t border-zinc-100 pt-3 text-sm" aria-label="{{ $navigationYear }} high value property months">
                    <span class="mr-2 text-xs font-semibold uppercase tracking-[0.14em] text-zinc-400">{{ $navigationYear }}</span>
                    @foreach ($availableMonths as $availableMonth)
                        <a href="{{ route('top-sales.show', ['year' => $availableMonth->format('Y'), 'month' => $availableMonth->format('m')], false) }}" data-high-value-month="{{ $availableMonth->format('Y-m') }}" class="border-b-2 px-2 py-1 font-medium {{ $month->isSameMonth($availableMonth) ? 'border-lime-600 bg-lime-50 text-lime-700' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900' }}" @if ($month->isSameMonth($availableMonth)) aria-current="page" @endif>{{ $availableMonth->format('M') }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
        <img src="{{ asset('assets/images/site/property1.jpg') }}" alt="" class="hidden h-48 justify-self-end object-contain opacity-80 mix-blend-multiply [mask-image:linear-gradient(to_right,transparent,black_22%)] md:block">
    </div>
</section>

<div class="mx-auto grid max-w-7xl gap-7 py-8">
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        <article class="border border-lime-200 bg-lime-50 p-6 shadow-sm md:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-lime-800">Top 10% entry price</p>
            <p class="mt-3 text-4xl font-bold tracking-tight text-lime-800">{{ $money($threshold) }}</p>
            <p class="mt-2 text-sm leading-5 text-zinc-600">Price required to enter the top 10% of the market this month</p>
            <div class="mt-4 flex gap-4 text-xs text-zinc-500"><span><b class="text-zinc-700">{{ $change($comparison['previous_change']) }}</b> vs {{ $comparison['previous_label'] }}</span><span><b class="text-zinc-700">{{ $change($comparison['year_change']) }}</b> vs {{ $comparison['year_label'] }}</span></div>
        </article>
        @foreach ([
            ['High-value sales', number_format($headline['sales'])],
            ['Segment median', $money($headline['median_price'])],
            ['Highest sale', $money($headline['highest_sale'])],
            ['Total value', $compactMoney($headline['total_value'])],
        ] as [$label, $value])
            <article class="flex flex-col justify-center border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500">{{ $label }}</p>
                <p class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950">{{ $value }}</p>
            </article>
        @endforeach
    </section>
    <p class="-mt-4 text-right text-sm text-zinc-500">The high-value segment represented <strong class="text-zinc-800">{{ number_format($headline['value_share'], 1) }}%</strong> of all residential transaction value.</p>

    @if (count($thresholdTrend) > 1)
        <section class="border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Recent context</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-950">90th percentile threshold trend</h2>
            <div class="mt-5 h-64"><canvas id="threshold-trend"></canvas></div>
        </section>
    @endif

    <section class="overflow-hidden border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-zinc-100 p-6 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Geography</p><h2 class="mt-2 text-2xl font-semibold text-zinc-950">High Value Property map</h2><p class="mt-1 text-sm text-zinc-500">District-centred aggregates; no raw transaction markers are sent to the browser.</p></div>
            <div class="inline-flex self-start border border-zinc-200 bg-zinc-50 p-1 text-xs font-medium">
                @foreach (['sales' => 'Number of sales', 'median' => 'Median price', 'value' => 'Total value'] as $mode => $label)
                    <button type="button" data-map-mode="{{ $mode }}" class="high-value-map-mode px-3 py-2 {{ $mode === 'sales' ? 'bg-zinc-900 text-white' : 'text-zinc-600' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @if (count($mapPoints))
            <div id="high-value-map" class="h-96 w-full bg-zinc-100 md:h-[34rem]"></div>
        @else
            <div class="grid h-64 place-items-center bg-zinc-50 px-6 text-center text-sm text-zinc-500">Map coordinates are not available in this environment.</div>
        @endif
    </section>

    <section class="overflow-hidden border border-zinc-200 bg-white shadow-sm">
        <div class="p-6"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Individual transactions</p><h2 class="mt-2 text-2xl font-semibold text-zinc-950">Top Property Sales This Month</h2><p class="mt-1 text-sm text-zinc-500">Select an address to investigate its complete PropertyResearch record.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-[860px] w-full border-t border-zinc-100 text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500"><tr><th class="px-4 py-3">Rank</th><th class="px-4 py-3">Address</th><th class="px-4 py-3">Price</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Tenure</th><th class="px-4 py-3">Area / postcode</th><th class="px-4 py-3">Sale date</th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($topSales as $index => $sale)
                        <tr class="hover:bg-zinc-50"><td class="px-4 py-4 font-semibold text-zinc-400">{{ $index + 1 }}</td><td class="px-4 py-4"><a href="{{ route('property.show.slug', ['slug' => $sale['property_slug']], false) }}" class="font-semibold text-lime-700 hover:underline">{{ $sale['address'] ?: $sale['postcode'] }}</a></td><td class="px-4 py-4 font-semibold text-zinc-950">{{ $money($sale['price']) }}</td><td class="px-4 py-4 text-zinc-600">{{ $typeNames[$sale['property_type']] ?? 'Other' }}</td><td class="px-4 py-4 text-zinc-600">{{ $tenureNames[$sale['tenure']] ?? 'Other' }}</td><td class="px-4 py-4 text-zinc-600">{{ $sale['area'] }}<br><span class="text-xs text-zinc-400">{{ $sale['postcode'] }}</span></td><td class="px-4 py-4 text-zinc-600">{{ $sale['date'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
        <article class="border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Market composition</p><h2 class="mt-2 text-xl font-semibold text-zinc-950">What sold at the top of the market?</h2>
            <div class="mt-6 grid gap-5">
                @foreach ($propertyTypes as $type)
                    <div><div class="flex justify-between gap-4 text-sm"><span class="font-medium text-zinc-800">{{ $type['label'] }}</span><span class="text-zinc-500">{{ number_format($type['count']) }} · {{ number_format($type['share'], 1) }}% · {{ $money($type['median_price']) }} median</span></div><div class="mt-2 h-2 bg-zinc-100"><div class="h-full bg-lime-600" style="width: {{ $maxType > 0 ? ($type['count'] / $maxType) * 100 : 0 }}%"></div></div></div>
                @endforeach
            </div>
        </article>
        <article class="border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Tenure</p><h2 class="mt-2 text-xl font-semibold text-zinc-950">Freehold and leasehold</h2>
            <div class="mt-6 divide-y divide-zinc-100">
                @foreach ($tenure as $item)
                    <div class="grid grid-cols-[1fr_auto] gap-4 py-5 first:pt-0"><div><p class="font-semibold text-zinc-900">{{ $item['label'] }}</p><p class="mt-1 text-sm text-zinc-500">{{ number_format($item['count']) }} sales · {{ number_format($item['share'], 1) }}%</p></div><p class="font-semibold text-lime-700">{{ $money($item['median_price']) }}</p></div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        @foreach ([['Most high-value transactions', $mostSalesHotspots, 'high_value_sales', 'sales'], ['Highest concentration of high-value sales', $highestConcentrationHotspots, 'concentration', '%']] as [$title, $rows, $key, $suffix])
            <article class="border border-zinc-200 bg-white p-6 shadow-sm"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">High Value Hotspots</p><h2 class="mt-2 text-xl font-semibold text-zinc-950">{{ $title }}</h2><div class="mt-5 divide-y divide-zinc-100">
                @forelse ($rows as $index => $row)<div class="grid grid-cols-[2rem_1fr_auto] items-center gap-3 py-3"><span class="text-xs font-semibold text-zinc-400">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><div><p class="font-medium text-zinc-800">{{ Str::title(Str::lower($row['district'])) }}</p><p class="text-xs text-zinc-400">{{ number_format($row['all_sales']) }} total sales</p></div><span class="font-semibold text-zinc-950">{{ number_format($row[$key], $key === 'concentration' ? 1 : 0) }}{{ $suffix === '%' ? '%' : ' '.$suffix }}</span></div>@empty<p class="py-6 text-sm text-zinc-500">Not enough district data is available.</p>@endforelse
            </div></article>
        @endforeach
    </section>

    <section class="border border-zinc-200 bg-zinc-950 p-6 text-white shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-lime-400">Supplementary benchmark</p><h2 class="mt-2 text-2xl font-semibold">The £1 Million Market</h2>
        <div class="mt-6 grid gap-px bg-white/10 sm:grid-cols-4">
            @foreach ([1000000 => '£1m+', 2000000 => '£2m+', 5000000 => '£5m+', 10000000 => '£10m+'] as $level => $label)<div class="bg-zinc-950 p-5"><p class="text-sm text-zinc-400">{{ $label }}</p><p class="mt-2 text-3xl font-semibold">{{ number_format($millionMarket['counts'][(string) $level]) }}</p></div>@endforeach
        </div>
        <div class="mt-6 grid gap-4 text-sm text-zinc-300 md:grid-cols-3"><p><strong class="text-white">{{ number_format($millionMarket['london_share'], 1) }}%</strong> of £1m+ sales were in London.</p>@if ($millionMarket['outside_london'])<p>Highest outside London: <strong class="text-white">{{ $money($millionMarket['outside_london']['price']) }}</strong> in {{ $millionMarket['outside_london']['area'] }}.</p>@endif @if ($millionMarket['top_area'])<p>Most £1m+ sales: <strong class="text-white">{{ Str::title(Str::lower($millionMarket['top_area']['district'])) }}</strong> ({{ number_format($millionMarket['top_area']['sales']) }}).</p>@endif</div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <article class="border border-zinc-200 bg-white p-6 shadow-sm"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Monthly observations</p><h2 class="mt-2 text-xl font-semibold text-zinc-950">What the data says</h2><ul class="mt-5 grid gap-4">@foreach ($observations as $fact)<li class="grid grid-cols-[auto_1fr] gap-3 text-sm leading-6 text-zinc-700"><span class="mt-2 size-2 bg-lime-600"></span>{{ $fact }}</li>@endforeach</ul></article>
        <article class="border border-zinc-200 bg-white p-6 shadow-sm"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Historic context</p><h2 class="mt-2 text-xl font-semibold text-zinc-950">Record Property Sales</h2><div class="mt-5 divide-y divide-zinc-100">@foreach ($recordSales as $sale)<div class="py-4 first:pt-0"><a href="{{ route('property.show.slug', ['slug' => $sale['property_slug']], false) }}" class="font-semibold text-lime-700 hover:underline">{{ $sale['address'] }}</a><p class="mt-1 text-sm text-zinc-500">{{ $money($sale['price']) }} · {{ $sale['area'] }} · {{ $sale['date'] }}</p></div>@endforeach</div></article>
    </section>

    <aside class="border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950"><strong>Methodology:</strong> Category A arm’s-length residential sales only. The benchmark is the selected month’s national 90th percentile; every metric above is recalculated from the current database and may change as HM Land Registry backfills records.</aside>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const trendElement = document.getElementById('threshold-trend');
    if (trendElement) {
        const trend = @json($thresholdTrend);
        new Chart(trendElement, {type: 'line', data: {labels: trend.map(point => point.label), datasets: [{data: trend.map(point => point.value), borderColor: '#65a30d', backgroundColor: 'rgba(101,163,13,.08)', fill: true, tension: .25, pointRadius: 2}]}, options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {ticks: {callback: value => '£' + Number(value).toLocaleString()}}}}});
    }
    const mapElement = document.getElementById('high-value-map');
    if (!mapElement) return;
    const points = @json($mapPoints);
    const map = L.map(mapElement, {scrollWheelZoom: false}).setView([52.8, -1.5], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap contributors'}).addTo(map);
    const layer = L.layerGroup().addTo(map);
    const metric = (point, mode) => mode === 'sales' ? point.high_value_sales : mode === 'median' ? point.median_price : point.total_value;
    const render = mode => {
        layer.clearLayers();
        const maximum = Math.max(...points.map(point => metric(point, mode)), 1);
        points.forEach(point => {
            const value = metric(point, mode);
            const median = point.median_price === null ? 'Unavailable' : '£' + Number(point.median_price).toLocaleString();
            L.circleMarker([point.lat, point.lng], {radius: 6 + Math.sqrt(value / maximum) * 20, color: '#fff', weight: 1.5, fillColor: mode === 'sales' ? '#65a30d' : mode === 'median' ? '#0284c7' : '#7c3aed', fillOpacity: .8})
                .bindPopup('<strong>' + point.district + '</strong><br>' + Number(point.high_value_sales).toLocaleString() + ' high-value sales<br>Median: ' + median + '<br>Total value: £' + Number(point.total_value).toLocaleString()).addTo(layer);
        });
    };
    document.querySelectorAll('.high-value-map-mode').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('.high-value-map-mode').forEach(item => { item.classList.remove('bg-zinc-900', 'text-white'); item.classList.add('text-zinc-600'); });
        button.classList.add('bg-zinc-900', 'text-white'); button.classList.remove('text-zinc-600'); render(button.dataset.mapMode);
    }));
    render('sales');
});
</script>
@endpush
