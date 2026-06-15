@extends('layouts.app')
@include('partials.chartjs-head')

@php
    $displayStreetName = \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($streetName));
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => url('/'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Property',
                'item' => route('property.home'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => 'Streets',
                'item' => route('property.search'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 4,
                'name' => $outcode,
            ],
            [
                '@type' => 'ListItem',
                'position' => 5,
                'name' => $displayStreetName,
                'item' => $canonicalUrl,
            ],
        ],
    ];
    $faqSchema = $faqItems === [] ? null : [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($faqItems)->map(fn (array $item): array => [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['answer'],
            ],
        ])->all(),
    ];
    $webPageSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $metaTitle,
        'description' => $metaDescription,
        'url' => $canonicalUrl,
        'dateModified' => $pageLastModified,
    ]);
    $comparisonRows = [
        ['label' => 'Average sale price', 'street' => $outcodeComparison['street']['average_sale_price'] ?? null, 'outcode' => $outcodeComparison['outcode']['average_sale_price'] ?? null, 'currency' => true],
        ['label' => 'Median sale price', 'street' => $outcodeComparison['street']['median_sale_price'] ?? null, 'outcode' => $outcodeComparison['outcode']['median_sale_price'] ?? null, 'currency' => true],
        ['label' => 'Sales count', 'street' => $outcodeComparison['street']['sales_count'] ?? null, 'outcode' => $outcodeComparison['outcode']['sales_count'] ?? null, 'currency' => false],
        ['label' => 'Most common property type', 'street' => $outcodeComparison['street']['most_common_property_type'] ?? null, 'outcode' => $outcodeComparison['outcode']['most_common_property_type'] ?? null, 'currency' => false],
    ];
@endphp

@section('title', $metaTitle.' | PropertyResearch.uk')
@section('description', $metaDescription)
@section('meta')
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta name="robots" content="{{ $limitedData ? 'noindex, follow' : 'index, follow' }}">
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if($faqSchema !== null)
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    <script type="application/ld+json">{!! json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
    <nav aria-label="Breadcrumb" class="mb-6">
        <ol class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
            <li><a href="{{ url('/') }}" class="hover:text-lime-700">Home</a></li>
            <li>/</li>
            <li><a href="{{ route('property.home') }}" class="hover:text-lime-700">Property</a></li>
            <li>/</li>
            <li><a href="{{ route('property.search') }}" class="hover:text-lime-700">Streets</a></li>
            <li>/</li>
            <li>{{ $outcode }}</li>
            <li>/</li>
            <li class="text-zinc-900">{{ $displayStreetName }}</li>
        </ol>
    </nav>

    <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Street Property Data</p>
                <h1 class="mt-2 text-2xl font-semibold text-zinc-900">{{ $displayStreetName }} {{ $outcode }} Sold Prices &amp; Property Data</h1>
                <p class="mt-2 text-sm text-zinc-600">
                    View sold house prices, recent transactions, property mix, and local context for {{ $displayStreetName }}, {{ $outcode }}.
                </p>

                @if($limitedData)
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        There is limited sales data for this street, so wider postcode district figures may give a better view of the local market.
                    </div>
                @endif
            </div>

            <div class="shrink-0">
                <img src="{{ asset('assets/images/site/street.png') }}" alt="Street property sales" class="h-auto w-90">
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Street Snapshot</p>
            <h2 class="text-xl font-semibold text-zinc-900">{{ $displayStreetName }} at a glance</h2>
            <p class="text-sm text-zinc-600">Only metrics with enough underlying data are shown here.</p>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($glanceMetrics as $metric)
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5">
                    <div class="text-xs uppercase tracking-[0.2em] text-zinc-500">{{ $metric['label'] }}</div>
                    <div class="mt-2 text-lg font-semibold text-zinc-900">{{ $metric['value'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-2">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Comparison</p>
            <h2 class="text-xl font-semibold text-zinc-900">{{ $displayStreetName }} compared with {{ $outcode }}</h2>
            <p class="text-sm text-zinc-600">Street-level figures are compared against the full postcode district using recorded Land Registry sales.</p>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-left text-zinc-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Metric</th>
                        <th class="px-3 py-2 font-medium">{{ $displayStreetName }}</th>
                        <th class="px-3 py-2 font-medium">{{ $outcode }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($comparisonRows as $row)
                        @continue($row['street'] === null && $row['outcode'] === null)
                        <tr class="border-t border-zinc-200">
                            <td class="px-3 py-2 font-medium text-zinc-700">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-zinc-900">
                                @if($row['currency'])
                                    {{ $row['street'] !== null ? '£'.number_format((int) $row['street']) : 'N/A' }}
                                @else
                                    {{ $row['street'] !== null ? $row['street'] : 'N/A' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-zinc-900">
                                @if($row['currency'])
                                    {{ $row['outcode'] !== null ? '£'.number_format((int) $row['outcode']) : 'N/A' }}
                                @else
                                    {{ $row['outcode'] !== null ? $row['outcode'] : 'N/A' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Chart</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-900">Yearly median price</h2>
            </div>
            <div class="mt-6 h-72">
                <canvas id="streetMedianPriceChart" class="h-full w-full"></canvas>
            </div>
        </article>

        <article class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Chart</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-900">Yearly sales count</h2>
            </div>
            <div class="mt-6 h-72">
                <canvas id="streetSalesCountChart" class="h-full w-full"></canvas>
            </div>
        </article>
    </section>

    @if(! empty($crimeTrend))
        <section class="mt-6 rounded-2xl border border-zinc-200 bg-white p-6 shadow-lg">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-zinc-600">Crime trends near {{ $displayStreetName }}, {{ $outcode }}</h2>

                @if($crimeDirection === 'rising')
                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
                        Rising
                    </span>
                @elseif($crimeDirection === 'falling')
                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">
                        Improving
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600">
                        Stable
                    </span>
                @endif
            </div>

            <p class="mb-2 text-base font-medium text-zinc-800">{{ $crimeSummary }}</p>
            <p class="mb-2 text-sm text-zinc-500">Compared with the previous 12 months, based on reported crimes within roughly 500 metres of this street centroid.</p>
            <p class="mb-1 text-xs text-zinc-500">Monthly crime volume over the last 24 months</p>

            <div class="mb-1 h-10">
                <canvas id="crimeSparkline" class="h-10 w-full"></canvas>
            </div>

            <div class="mb-4 mt-1 flex items-center justify-between gap-3 text-xs text-zinc-500">
                <span>Peaks indicate higher reported crime periods.</span>
                <span>Latest: {{ collect($crimeTrendValues)->last() ?? 0 }} crimes</span>
            </div>

            <div class="grid grid-cols-1 gap-6 text-sm md:grid-cols-3">
                <div>
                    <div class="text-zinc-500">Overall change</div>
                    <div class="text-2xl font-bold {{ $totalChange > 0 ? 'text-red-600' : ($totalChange < 0 ? 'text-green-600' : 'text-zinc-700') }}">
                        {{ $totalChange > 0 ? '+' : '' }}{{ $totalChange }}%
                    </div>
                </div>

                <div>
                    <div class="text-zinc-500">Rising most</div>
                    <div class="font-semibold">{{ $topIncrease['crime_type'] ?? '-' }}</div>
                    <div class="text-sm text-zinc-600">{{ $topIncrease['pct_change_label'] ?? (($topIncrease['pct_change'] ?? 0).'%') }}</div>
                </div>

                <div>
                    <div class="text-zinc-500">Falling most</div>
                    <div class="font-semibold">{{ $topDecrease['crime_type'] ?? '-' }}</div>
                    <div class="text-sm text-zinc-600">{{ $topDecrease['pct_change'] ?? 0 }}%</div>
                </div>
            </div>

            @if(! empty($crimeData))
                <div class="mt-6">
                    <details class="rounded-xl border border-zinc-200 bg-white px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-zinc-600 marker:text-zinc-500">
                            Crime profile for this street area
                        </summary>

                        <div class="mt-4 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left">
                                        <th class="py-2">Crime type</th>
                                        <th class="py-2">Total</th>
                                        <th class="py-2">%</th>
                                        <th class="py-2">12m change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($crimeData as $crime)
                                        <tr class="border-b border-zinc-100">
                                            <td class="py-2">{{ $crime['crime_type'] }}</td>
                                            <td class="py-2">{{ $crime['total'] }}</td>
                                            <td class="py-2">{{ $crime['pct'] }}%</td>
                                            <td class="py-2">
                                                <span class="{{ ($crime['pct_change'] ?? 0) > 0 ? 'text-red-600' : (($crime['pct_change'] ?? 0) < 0 ? 'text-green-600' : 'text-zinc-500') }}">
                                                    {{ ($crime['pct_change'] ?? 0) > 0 ? '+' : '' }}{{ $crime['pct_change'] ?? 0 }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            @endif
        </section>
    @endif

    @php
        $badgeClass = function ($decile) {
            $d = (int) ($decile ?? 0);
            if ($d <= 0) return 'bg-zinc-100 text-zinc-800 border-zinc-200';
            if ($d <= 2) return 'bg-rose-100 text-rose-800 border-rose-200';
            if ($d <= 4) return 'bg-amber-100 text-amber-800 border-amber-200';
            if ($d <= 6) return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            if ($d <= 8) return 'bg-lime-100 text-lime-800 border-lime-200';
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        };
    @endphp

    <section class="mt-6">
        @if($depr)
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-lg">
                <div class="grid gap-6 lg:grid-cols-[1.2fr,0.8fr]">
                    <div>
                        <div class="text-lg font-bold text-zinc-600">Closest deprivation area for this street</div>
                        <div class="font-medium">
                            {{ $depr['name'] }}
                            <span class="text-xs text-zinc-500">({{ $depr['lsoa21'] }})</span>
                        </div>
                        <div class="pt-1 text-xs text-zinc-500">
                            This is the nearest mapped LSOA to the street centroid, so it provides area context rather than street-specific deprivation.
                        </div>

                        <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="mb-1 text-xs text-zinc-600">Decile</div>
                                    <span class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-bold shadow-sm {{ $badgeClass($depr['decile']) }}">
                                        {{ $depr['decile'] ? $depr['decile'].' / 10' : 'N/A' }}
                                    </span>
                                </div>
                                <div>
                                    <div class="mb-1 text-xs text-zinc-600">Rank</div>
                                    <div class="text-2xl font-semibold leading-none">
                                        @if($depr['rank'])
                                            {{ number_format($depr['rank']) }} <span class="text-sm font-normal text-zinc-500">/ {{ number_format($depr['total'] ?? 32844) }}</span>
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3">
                            @if($lsoaLink)
                                <a href="{{ $lsoaLink }}" class="inline-flex items-center gap-1.5 rounded-md border border-lime-200 bg-zinc-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-500">Full details</a>
                            @endif
                            @if(! empty($depr['postcode']))
                                <span class="inline-flex items-center rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-xs text-zinc-500">
                                    Nearest postcode anchor: {{ $depr['postcode'] }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-medium text-zinc-700">Closest LSOA map context</div>
                        <div class="relative overflow-hidden rounded-xl border border-zinc-200">
                            <div id="street-deprivation-map" class="h-72 w-full bg-zinc-100"></div>
                            <div id="street-deprivation-map-loading" class="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/80 text-sm text-zinc-500">
                                Loading map...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $deprMsg ?? 'Unable to resolve this street to an English or Welsh LSOA.' }}
            </div>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Recent Sales</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-900">Recent property sales on {{ $displayStreetName }}</h2>
                <p class="mt-2 text-sm text-zinc-600">This is crawlable HTML and links through to the matching property page where a slug is available.</p>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 text-left text-zinc-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Date</th>
                        <th class="px-3 py-2 font-medium">Price</th>
                        <th class="px-3 py-2 font-medium">Property type</th>
                        <th class="px-3 py-2 font-medium">New build / resale</th>
                        <th class="px-3 py-2 font-medium">Tenure</th>
                        <th class="px-3 py-2 font-medium">Address</th>
                        <th class="px-3 py-2 font-medium">View</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $sale)
                        <tr class="border-t border-zinc-200">
                            <td class="whitespace-nowrap px-3 py-2 text-zinc-700">{{ $sale['date_label'] ?? 'N/A' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-zinc-900">{{ $sale['price_label'] ?? 'N/A' }}</td>
                            <td class="px-3 py-2 text-zinc-700">{{ $sale['property_type'] }}</td>
                            <td class="px-3 py-2 text-zinc-700">{{ $sale['build_status'] }}</td>
                            <td class="px-3 py-2 text-zinc-700">{{ $sale['tenure'] }}</td>
                            <td class="px-3 py-2 text-zinc-900">
                                <div>{{ $sale['address'] !== '' ? $sale['address'] : 'N/A' }}</div>
                                <div class="text-xs text-zinc-500">{{ $streetName }}, {{ $sale['postcode'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-center">
                                @if(! empty($sale['property_slug']))
                                    <a
                                        href="{{ route('property.show.slug', ['slug' => $sale['property_slug']]) }}"
                                        class="inline-flex items-center justify-center rounded-md bg-zinc-700 p-2 text-white hover:bg-zinc-500"
                                        title="View property details"
                                        aria-label="View property details"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.387a1 1 0 01-1.414 1.414l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="inline-flex items-center justify-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-500">
                                        Unavailable
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $sales->links() }}
        </div>
    </section>

    @if($nearbyStreets !== [])
        <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Internal Links</p>
                <h2 class="text-lg font-semibold text-zinc-900">Nearby streets in {{ $outcode }}</h2>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($nearbyStreets as $nearbyStreet)
                    <a href="{{ $nearbyStreet['url'] }}" class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 transition hover:border-lime-300 hover:text-lime-700">
                        <span class="block font-medium text-zinc-900">{{ $nearbyStreet['name'] }}</span>
                        <span class="mt-1 block text-xs text-zinc-500">{{ number_format($nearbyStreet['sales_count']) }} recorded sales</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($faqItems !== [])
        <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">FAQ</p>
                <h2 class="text-lg font-semibold text-zinc-900">Questions about {{ $displayStreetName }}</h2>
            </div>

            <div class="mt-6 grid gap-4">
                @foreach($faqItems as $item)
                    <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <h3 class="text-sm font-semibold text-zinc-900">{{ $item['question'] }}</h3>
                        <p class="mt-2 text-sm text-zinc-600">{{ $item['answer'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
    (function () {
        const yearlyMedianPrice = @json($yearlyMedianPrice);
        const yearlySalesCount = @json($yearlySalesCount);
        const crimeSparklineEl = document.getElementById('crimeSparkline');
        const crimeTrendColor = @json($totalChange > 0 ? '#ef4444' : ($totalChange < 0 ? '#16a34a' : '#3b82f6'));
        const deprivationMapEl = document.getElementById('street-deprivation-map');

        const currencyFormatter = new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            maximumFractionDigits: 0
        });

        const countFormatter = new Intl.NumberFormat('en-GB');

        const renderChart = function (canvasId, dataset, config) {
            const canvas = document.getElementById(canvasId);

            if (!canvas || !Array.isArray(dataset) || dataset.length === 0) {
                return;
            }

            new Chart(canvas.getContext('2d'), {
                type: config.type,
                data: {
                    labels: dataset.map(function (item) { return item.year; }),
                    datasets: [{
                        label: config.label,
                        data: dataset.map(function (item) { return item.value; }),
                        borderColor: config.borderColor,
                        backgroundColor: config.backgroundColor,
                        borderWidth: 2,
                        fill: config.fill,
                        tension: 0.25,
                        maxBarThickness: 36
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return config.tooltip(context.parsed.y ?? 0);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#52525b' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#52525b',
                                callback: function (value) {
                                    return config.axis(value);
                                }
                            }
                        }
                    }
                }
            });
        };

        if (deprivationMapEl && typeof L !== 'undefined') {
            const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            });

            const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles © Esri, Maxar, Earthstar Geographics'
            });

            const map = L.map('street-deprivation-map', {
                center: [{{ $depr['lat'] ?? 0 }}, {{ $depr['long'] ?? 0 }}],
                zoom: 14,
                layers: [osm]
            });

            L.marker([{{ $depr['lat'] ?? 0 }}, {{ $depr['long'] ?? 0 }}]).addTo(map)
                .bindPopup('Closest LSOA context for this street')
                .openPopup();

            L.control.layers({
                'Map': osm,
                'Satellite': satellite
            }).addTo(map);

            const loadingEl = document.getElementById('street-deprivation-map-loading');
            if (loadingEl) {
                osm.on('load', function () {
                    loadingEl.style.transition = 'opacity 200ms ease-out';
                    loadingEl.style.opacity = '0';
                    setTimeout(function () {
                        loadingEl.remove();
                    }, 220);
                });
            }
        }

        if (crimeSparklineEl) {
            new Chart(crimeSparklineEl, {
                type: 'line',
                data: {
                    labels: @json($crimeTrendLabels ?? []),
                    datasets: [{
                        data: @json($crimeTrendValues ?? []),
                        borderColor: crimeTrendColor,
                        borderWidth: 1.5,
                        tension: 0.25,
                        pointRadius: function (context) {
                            const index = context.dataIndex;
                            const total = context.dataset.data.length;

                            return index === 0 || index === total - 1 ? 2 : 0;
                        },
                        pointBackgroundColor: crimeTrendColor,
                        fill: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: undefined,
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function (context) {
                                    return context.raw + ' crimes';
                                }
                            }
                        },
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false },
                    }
                }
            });
        }

        renderChart('streetMedianPriceChart', yearlyMedianPrice, {
            type: 'line',
            label: 'Median sale price',
            borderColor: '#65a30d',
            backgroundColor: 'rgba(101, 163, 13, 0.16)',
            fill: true,
            tooltip: function (value) { return currencyFormatter.format(value); },
            axis: function (value) { return '£' + countFormatter.format(value); }
        });

        renderChart('streetSalesCountChart', yearlySalesCount, {
            type: 'bar',
            label: 'Sales count',
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.72)',
            fill: false,
            tooltip: function (value) { return countFormatter.format(value) + ' sales'; },
            axis: function (value) { return countFormatter.format(value); }
        });
    })();
</script>
@endpush
@endsection
