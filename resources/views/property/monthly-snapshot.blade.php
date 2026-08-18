@extends('layouts.app')

@section('title', $month->format('F Y').' Property Market Snapshot | PropertyResearch.uk')
@section('description', 'A visual snapshot of England and Wales property sales recorded by HM Land Registry in '.$month->format('F Y').'.')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
@endpush

@section('content')
@php
    $formatPrice = fn (?int $value): string => $value === null ? '—' : '£'.number_format($value);
    $typeColours = ['bg-lime-600', 'bg-sky-600', 'bg-amber-500', 'bg-violet-500', 'bg-zinc-400'];
    $typeHexColours = ['#65a30d', '#0284c7', '#f59e0b', '#8b5cf6', '#a1a1aa'];
    $typeGradientStops = [];
    $typeGradientStart = 0;

    foreach ($propertyTypes as $index => $type) {
        $typeGradientEnd = $typeGradientStart + $type['share'];
        $typeGradientStops[] = $typeHexColours[$index].' '.$typeGradientStart.'% '.$typeGradientEnd.'%';
        $typeGradientStart = $typeGradientEnd;
    }

    $typeGradient = implode(', ', $typeGradientStops);
    $typeChartLabel = collect($propertyTypes)
        ->map(fn (array $type): string => $type['label'].' '.$type['share'].' percent')
        ->implode(', ');
    $maximumPriceBandSales = max(array_column($priceBands, 'sales') ?: [0]);
    $maximumDistrictSales = max(array_column($topDistricts, 'sales') ?: [0]);
    $propertyTypeNames = ['D' => 'Detached', 'S' => 'Semi-detached', 'T' => 'Terraced', 'F' => 'Flat / maisonette', 'O' => 'Other'];
    $formatChange = fn (?float $value): string => $value === null
        ? '—'
        : ($value > 0 ? '+' : '').number_format($value, 1).'%';
    $changeClass = fn (?float $value): string => match (true) {
        $value === null, abs($value) < 0.1 => 'text-zinc-500',
        $value > 0 => 'text-emerald-700',
        default => 'text-rose-700',
    };
@endphp

<section class="relative z-0 -mx-6 -mt-6 overflow-hidden bg-white py-8 shadow-[0_10px_24px_-20px_rgba(15,23,42,0.18)] md:py-9">
    <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[minmax(0,1fr)_minmax(280px,0.42fr)] md:gap-8">
        <div class="max-w-4xl">
            <div class="inline-flex items-center gap-2 text-xs font-medium text-zinc-600">
                <span class="h-2 w-2 rounded-full bg-lime-600"></span>
                England and Wales transaction data
            </div>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-950 md:text-4xl">{{ $month->format('F Y') }} Property Snapshot</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600">A focused look at the <span class="font-bold text-lime-600">Category A</span> residential transactions uploaded for England and Wales in the latest Land Registry month.</p>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600"><span class="font-semibold text-amber-700">Provisional data:</span> HM Land Registry routinely backfills its latest three months. This {{ $month->format('F Y') }} snapshot updates automatically as further records arrive, so the figures may change over the next few monthly releases.</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <a href="{{ route('property.home', absolute: false) }}" class="inner-button bg-lime-600! hover:bg-lime-700!">Property Dashboard</a>
                <div class="border-l border-zinc-200 pl-3">
                    <span class="text-2xl font-semibold tabular-nums text-zinc-950">{{ number_format($sales) }}</span>
                    <span class="ml-1 text-sm text-zinc-500">recorded sales</span>
                </div>
            </div>
            @if (count($snapshotMonths) > 0)
                <nav class="mt-4 flex flex-wrap items-center gap-x-1 gap-y-2 border-t border-zinc-100 pt-3 text-sm" aria-label="{{ $snapshotNavigationYear }} property snapshots">
                    <span class="mr-2 text-xs font-semibold uppercase tracking-[0.14em] text-zinc-400">{{ $snapshotNavigationYear }} snapshots</span>
                    @foreach ($snapshotMonths as $snapshotMonth)
                        @php
                            $isActiveMonth = $month->isSameMonth($snapshotMonth);
                        @endphp
                        <a
                            href="{{ route('property.monthly-snapshot.show', ['year' => $snapshotMonth->format('Y'), 'month' => $snapshotMonth->format('m')], absolute: false) }}"
                            data-snapshot-month="{{ $snapshotMonth->format('Y-m') }}"
                            class="border-b-2 px-2 py-1 font-medium transition-colors {{ $isActiveMonth ? 'border-lime-600 bg-lime-50 text-lime-700' : 'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-900' }}"
                            @if ($isActiveMonth) aria-current="page" @endif
                        >{{ $snapshotMonth->format('M') }}</a>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="hidden min-w-0 justify-end md:flex" aria-hidden="true">
            <img src="{{ asset('assets/images/site/property1.jpg') }}" alt="" class="h-44 w-auto max-w-full object-contain opacity-80 mix-blend-multiply [mask-image:linear-gradient(to_right,transparent_0%,black_18%,black_94%,transparent_100%)] lg:h-52">
        </div>
    </div>
</section>

<div class="mx-auto grid max-w-7xl gap-6 py-8">
    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm md:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Middle of the market</p>
            <p class="mt-3 text-4xl font-semibold tracking-tight text-zinc-950">{{ $formatPrice($medianPrice) }}</p>
            <p class="mt-2 text-sm text-zinc-500">Median sale price</p>
        </article>
        <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Upper market</p>
            <p class="mt-3 text-4xl font-semibold tracking-tight text-sky-700">{{ $formatPrice($percentile90) }}</p>
            <p class="mt-2 text-sm text-zinc-500">90th percentile</p>
        </article>
        <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Premium tail</p>
            <p class="mt-3 text-4xl font-semibold tracking-tight text-lime-700">{{ $formatPrice($top5Average) }}</p>
            <p class="mt-2 text-sm text-zinc-500">Average of the top 5%</p>
        </article>
    </section>

    <section class="overflow-hidden rounded-sm border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-100 px-6 py-5">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Monthly context</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-950">How does this month compare?</h2>
            <p class="mt-1 text-sm text-zinc-500">Headline indicators compared with the previous month and the same month last year.</p>
            <p class="mt-2 text-xs text-zinc-400">The current month may continue to be backfilled as HM Land Registry receives additional registrations in the coming months.</p>
        </div>
        <div class="grid sm:grid-cols-2 xl:grid-cols-4 xl:divide-x xl:divide-zinc-100">
            @foreach ([
                ['label' => 'Recorded sales', 'metric' => $comparison['sales'], 'format' => fn ($value) => number_format($value)],
                ['label' => 'Median sale price', 'metric' => $comparison['median_price'], 'format' => $formatPrice],
                ['label' => '90th percentile sale price', 'metric' => $comparison['p90_price'], 'format' => $formatPrice],
                ['label' => '£1m+ sales share', 'metric' => $comparison['million_plus_share'], 'format' => fn ($value) => number_format($value, 1).'%'],
            ] as $item)
                <article class="border-b border-zinc-100 p-6 sm:[&:nth-last-child(-n+2)]:border-b-0 xl:border-b-0">
                    <p class="text-sm font-medium text-zinc-600">{{ $item['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight text-zinc-950">{{ $item['format']($item['metric']['current']) }}</p>
                    <div class="mt-4 grid gap-1.5 text-xs tabular-nums">
                        <p class="{{ $changeClass($item['metric']['previous_change']) }}">
                            <span class="font-semibold">{{ $formatChange($item['metric']['previous_change']) }}</span>
                            <span class="text-zinc-400">vs {{ $comparison['previous_label'] }}</span>
                        </p>
                        <p class="{{ $changeClass($item['metric']['year_change']) }}">
                            <span class="font-semibold">{{ $formatChange($item['metric']['year_change']) }}</span>
                            <span class="text-zinc-400">vs {{ $comparison['year_label'] }}</span>
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Housing mix</p>
                    <h2 class="mt-2 text-2xl font-semibold text-zinc-950">What sold this month?</h2>
                </div>
                <span class="text-sm text-zinc-500">Share of {{ number_format($sales) }} sales</span>
            </div>
            <div class="relative mx-auto mt-7 aspect-square w-full max-w-56 rounded-full shadow-[inset_0_0_0_1px_rgba(24,24,27,0.04)]" style="background: conic-gradient(from -90deg, {{ $typeGradient }});" role="img" aria-label="Property type split: {{ $typeChartLabel }}">
                <div class="absolute inset-[22%] grid place-content-center rounded-full bg-white text-center shadow-[0_0_0_1px_rgba(24,24,27,0.04)]">
                    <span class="text-2xl font-semibold tabular-nums text-zinc-950">{{ number_format($sales) }}</span>
                    <span class="mt-0.5 text-[0.65rem] font-medium uppercase tracking-wide text-zinc-500">Total sales</span>
                </div>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                @foreach ($propertyTypes as $index => $type)
                    <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3">
                        <span class="h-3 w-3 rounded-full {{ $typeColours[$index] }}"></span>
                        <div>
                            <p class="font-medium text-zinc-900">{{ $type['label'] }}</p>
                            <p class="text-xs text-zinc-500">{{ number_format($type['sales']) }} sales · {{ number_format($type['share'], 1) }}%</p>
                        </div>
                        <p class="font-semibold tabular-nums text-zinc-900">{{ $formatPrice($type['median_price']) }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 border-t border-zinc-100 pt-4 text-right text-xs text-zinc-400">Figures on the right are median prices</div>
        </article>

        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-1">
            @foreach ([
                ['title' => 'New build vs existing', 'eyebrow' => 'Stock profile', 'items' => $newBuildMix, 'colours' => ['#14b8a6', '#0ea5e9'], 'dots' => ['bg-teal-500', 'bg-sky-500'], 'highlight' => 1],
                ['title' => 'Freehold vs leasehold', 'eyebrow' => 'Tenure mix', 'items' => $tenureMix, 'colours' => ['#65a30d', '#8b5cf6'], 'dots' => ['bg-lime-600', 'bg-violet-500'], 'highlight' => 0],
            ] as $mix)
                @php
                    $firstShare = $mix['items'][0]['share'];
                    $highlightedItem = $mix['items'][$mix['highlight']];
                @endphp
                <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">{{ $mix['eyebrow'] }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-950">{{ $mix['title'] }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">Share of sales this month</p>
                    </div>

                    <div class="mt-5 grid items-center gap-6 sm:grid-cols-[minmax(150px,0.8fr)_minmax(0,1fr)] xl:grid-cols-[minmax(150px,0.8fr)_minmax(0,1fr)]">
                        <div class="relative mx-auto aspect-square w-full max-w-48 rounded-full shadow-[inset_0_0_0_1px_rgba(24,24,27,0.04)]" style="background: conic-gradient(from -90deg, {{ $mix['colours'][0] }} 0 {{ $firstShare }}%, {{ $mix['colours'][1] }} {{ $firstShare }}% 100%);" role="img" aria-label="{{ $mix['items'][0]['label'] }} {{ $mix['items'][0]['share'] }} percent, {{ $mix['items'][1]['label'] }} {{ $mix['items'][1]['share'] }} percent">
                            <div class="absolute inset-[22%] grid place-content-center rounded-full bg-white text-center shadow-[0_0_0_1px_rgba(24,24,27,0.04)]">
                                <span class="text-2xl font-semibold tabular-nums text-zinc-950">{{ number_format($highlightedItem['share'], 1) }}%</span>
                                <span class="mt-0.5 text-[0.65rem] font-medium uppercase tracking-wide text-zinc-500">{{ $highlightedItem['label'] }}</span>
                            </div>
                        </div>

                        <div class="grid gap-4">
                            @foreach ($mix['items'] as $index => $item)
                                <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3 border-b border-zinc-100 pb-3 last:border-0 last:pb-0">
                                    <span class="h-3 w-3 rounded-full {{ $mix['dots'][$index] }}"></span>
                                    <div>
                                        <p class="font-medium text-zinc-900">{{ $item['label'] }}</p>
                                        <p class="text-xs text-zinc-500">{{ number_format($item['share'], 1) }}% of sales</p>
                                    </div>
                                    <p class="text-lg font-semibold tabular-nums text-zinc-950">{{ number_format($item['total']) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-sm border border-zinc-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-zinc-100 p-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Geographic activity</p>
                <h2 class="mt-2 text-2xl font-semibold text-zinc-950">Where sales were recorded</h2>
                <p class="mt-2 max-w-3xl text-sm text-zinc-500">District-centred activity derived from the coordinates of matched Land Registry postcodes in ONSPD.</p>
            </div>
            <div class="inline-flex self-start rounded-sm border border-zinc-200 bg-zinc-50 p-1 text-xs font-medium" aria-label="Map display">
                <button type="button" data-map-mode="sales" class="monthly-map-mode rounded-sm bg-zinc-900 px-3 py-1.5 text-white">Sales volume</button>
                <button type="button" data-map-mode="price" class="monthly-map-mode rounded-sm px-3 py-1.5 text-zinc-600 hover:text-zinc-950">Median price</button>
            </div>
        </div>
        <div id="monthly-district-map" class="h-96 w-full bg-zinc-100 md:h-[34rem]"></div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 px-6 py-3 text-xs text-zinc-500">
            <span id="monthly-map-description">Bubble size and colour show recorded sales volume.</span>
            <div class="flex items-center gap-2" aria-label="Map colour scale">
                <span>Lower</span>
                <span id="monthly-map-scale" class="h-2.5 w-28 rounded-full" style="background: linear-gradient(to right, #22c55e, #84cc16, #facc15, #f97316, #dc2626);"></span>
                <span>Higher</span>
            </div>
            <span>{{ number_format(count($districtMapPoints)) }} districts mapped · ONSPD postcode coordinates</span>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Market shape</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-950">Sales by price band</h2>
            <p class="mt-2 text-sm text-zinc-500">Where this month's recorded transactions sit across the price spectrum.</p>
            <div class="mt-6 grid gap-4">
                @foreach ($priceBands as $band)
                    <div>
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="font-medium text-zinc-700">{{ $band['label'] }}</span>
                            <span class="tabular-nums text-zinc-500">{{ number_format($band['sales']) }} · {{ number_format($band['share'], 1) }}%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100">
                            <div class="h-full rounded-full bg-sky-600" style="width: {{ $maximumPriceBandSales > 0 ? ($band['sales'] / $maximumPriceBandSales) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Activity leaders</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-950">Most active districts</h2>
            <p class="mt-2 text-sm text-zinc-500">Districts with the most Category A sales currently recorded.</p>
            <div class="mt-6 grid gap-3">
                @foreach ($topDistricts as $index => $district)
                    <div class="grid grid-cols-[1.5rem_minmax(0,1fr)_auto] items-center gap-3">
                        <span class="text-xs font-semibold tabular-nums text-zinc-400">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="min-w-0">
                            <div class="flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium text-zinc-800">{{ Str::title(Str::lower($district['district'])) }}</span>
                                <span class="text-xs tabular-nums text-zinc-500">{{ $formatPrice($district['median_price']) }} median</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-zinc-100">
                                <div class="h-full rounded-full bg-lime-600" style="width: {{ $maximumDistrictSales > 0 ? ($district['sales'] / $maximumDistrictSales) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                        <span class="text-sm font-semibold tabular-nums text-zinc-950">{{ number_format($district['sales']) }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
        <article class="rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">Regional price spread</p>
            <h2 class="mt-2 text-xl font-semibold text-zinc-950">Highest and lowest district medians</h2>
            <p class="mt-2 text-sm text-zinc-500">Only districts with at least 50 recorded sales are included.</p>
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                @foreach ([['title' => 'Highest', 'rows' => $highestPriceDistricts, 'colour' => 'text-lime-700'], ['title' => 'Lowest', 'rows' => $lowestPriceDistricts, 'colour' => 'text-sky-700']] as $group)
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-400">{{ $group['title'] }}</h3>
                        <div class="mt-3 divide-y divide-zinc-100">
                            @foreach ($group['rows'] as $district)
                                <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-zinc-800">{{ Str::title(Str::lower($district['district'])) }}</p>
                                        <p class="text-xs text-zinc-400">{{ number_format($district['sales']) }} sales</p>
                                    </div>
                                    <p class="font-semibold tabular-nums {{ $group['colour'] }}">{{ $formatPrice($district['median_price']) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="overflow-hidden rounded-sm border border-zinc-200 bg-white text-zinc-950 shadow-sm">
            <div class="p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-500">At the top of the market</p>
                <h2 class="mt-2 text-xl font-semibold">Notable sales</h2>
                <p class="mt-2 text-sm text-zinc-500">The three largest Category A transactions recorded this month.</p>
            </div>
            <div class="divide-y divide-zinc-100 border-t border-zinc-100">
                @foreach ($notableSales as $index => $sale)
                    <div class="grid grid-cols-[auto_1fr] gap-4 p-6">
                        <span class="text-xs font-semibold text-zinc-400">0{{ $index + 1 }}</span>
                        <div>
                            <p class="text-3xl font-semibold tracking-tight text-lime-700">{{ $formatPrice($sale['price']) }}</p>
                            <p class="mt-2 text-sm text-zinc-700">{{ $propertyTypeNames[$sale['property_type']] ?? 'Property' }} · {{ Str::title(Str::lower($sale['district'])) }}</p>
                            <p class="mt-1 text-xs text-zinc-400">{{ $sale['postcode'] }} · {{ $sale['date'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <aside class="flex flex-col gap-3 rounded-sm border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between">
        <p><strong>About this snapshot:</strong> Category A arm's-length sales only. The latest Land Registry month is provisional while records are backfilled.</p>
        <a href="/blog/category-a-vs-category-b-property-sales-what-the-land-registry-is-actually-telling-you" class="shrink-0 font-semibold text-amber-900 underline decoration-amber-400 underline-offset-4">How categories work</a>
    </aside>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapElement = document.getElementById('monthly-district-map');
    const points = @json($districtMapPoints);

    if (!mapElement || typeof L === 'undefined' || !points.length) {
        return;
    }

    const map = L.map(mapElement, { scrollWheelZoom: false }).setView([52.7, -1.7], 6);
    const layer = L.layerGroup().addTo(map);
    const formatNumber = new Intl.NumberFormat('en-GB');
    const formatPrice = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 });
    const maximumSales = Math.max(...points.map((point) => point.sales));
    const salesValues = points.map((point) => point.sales).sort((a, b) => a - b);
    const prices = points.map((point) => point.median_price).filter((price) => price !== null).sort((a, b) => a - b);
    const salesAt = (percentile) => salesValues[Math.min(salesValues.length - 1, Math.floor(salesValues.length * percentile))] || 0;
    const priceAt = (percentile) => prices[Math.min(prices.length - 1, Math.floor(prices.length * percentile))] || 0;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    const titleCase = (value) => value.toLowerCase().replace(/\b\w/g, (character) => character.toUpperCase());
    const salesColour = (sales) => sales >= salesAt(0.95) ? '#dc2626' : sales >= salesAt(0.85) ? '#f97316' : sales >= salesAt(0.7) ? '#facc15' : sales >= salesAt(0.5) ? '#84cc16' : '#22c55e';
    const priceColour = (price) => price >= priceAt(0.8) ? '#075985' : price >= priceAt(0.6) ? '#0284c7' : price >= priceAt(0.4) ? '#38bdf8' : price >= priceAt(0.2) ? '#7dd3fc' : '#bae6fd';

    const render = (mode) => {
        layer.clearLayers();
        const isSales = mode === 'sales';

        points.forEach((point) => {
            const colour = isSales ? salesColour(point.sales) : priceColour(point.median_price || 0);
            const radius = isSales ? 5 + (Math.sqrt(point.sales / maximumSales) * 20) : 10;
            const marker = L.circleMarker([point.lat, point.lng], {
                radius,
                color: '#ffffff',
                weight: 1.5,
                fillColor: colour,
                fillOpacity: 0.82,
            });

            marker.bindTooltip(
                '<strong>' + titleCase(point.district) + '</strong><br>' +
                formatNumber.format(point.sales) + ' sales<br>' +
                (point.median_price === null ? 'Median unavailable' : formatPrice.format(point.median_price) + ' median'),
                { direction: 'top' },
            );
            marker.addTo(layer);
        });

        document.getElementById('monthly-map-description').textContent = isSales
            ? 'Bubble size shows volume; colour moves from green to red as district sales increase.'
            : 'Colour shows median sale price; bubble sizes are fixed for easier comparison.';
        document.getElementById('monthly-map-scale').style.background = isSales
            ? 'linear-gradient(to right, #22c55e, #84cc16, #facc15, #f97316, #dc2626)'
            : 'linear-gradient(to right, #bae6fd, #7dd3fc, #38bdf8, #0284c7, #075985)';
    };

    document.querySelectorAll('.monthly-map-mode').forEach((button) => {
        button.addEventListener('click', function () {
            document.querySelectorAll('.monthly-map-mode').forEach((candidate) => {
                candidate.classList.remove('bg-zinc-900', 'text-white');
                candidate.classList.add('text-zinc-600');
            });
            this.classList.add('bg-zinc-900', 'text-white');
            this.classList.remove('text-zinc-600');
            render(this.dataset.mapMode);
        });
    });

    render('sales');
});
</script>
@endpush
