@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">

    {{-- Hero --}}
    <section class="relative z-0 overflow-hidden rounded-lg border border-zinc-200 bg-white px-8 py-8 shadow-sm md:min-h-[320px] md:pr-0">
        <div class="relative z-10 flex flex-col gap-6 md:flex-row md:items-center md:gap-2 lg:gap-3">
            <div class="md:max-w-4xl md:flex-[1.2] md:pr-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white/70 px-3 py-1 text-xs text-zinc-700 shadow-sm">
                        <span class="h-2 w-2 rounded-lg bg-lime-500"></span>
                        Independent UK property data
                    </div>
                    @auth
                        @if (Auth::id() === 1)
                            <div class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-1 text-xs font-medium text-emerald-800 shadow-sm">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400/70"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                </span>
                                Admin Online
                            </div>
                        @endif
                    @endauth
                </div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-3xl">
                    Explore property prices, sales history and local trends
                </h1>
                <p class="mt-3 text-md leading-7 text-zinc-500">
                    Search property sales, EPC certificates and local housing data. Check house prices, explore your street or postcode, 
                    and understand how the market is changing in your area.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-zinc-600">
                    <p>31 million property sales • 24 million EPC certificates • Updated monthly</p>
                </div>
            </div>

            <div class="mx-auto w-full max-w-[320px] md:mx-0 md:w-[31%] md:max-w-none md:min-w-[240px] lg:w-[33%]">
                <img
                    src="{{ asset('/assets/images/site/logo10.jpg') }}"
                    alt="Property Research"
                    width="768"
                    height="512"
                    class="h-auto w-full object-contain md:translate-x-8"
                >
            </div>
        </div>
    </section>

    <section class="mt-8">
        <div class="mx-auto grid max-w-5xl gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="mb-2 text-sm font-medium text-zinc-800">Search by street</div>
                <div class="relative">
                    <input
                        id="home-street-search"
                        type="text"
                        autocomplete="off"
                        placeholder="Search by street"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-zinc-900"
                    />
                    <div
                        id="home-street-suggestions"
                        class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white text-sm shadow-lg">
                    </div>
                </div>

                <div class="mt-2 text-xs text-zinc-500">
                    Autocomplete matches unique street and postcode district combinations from Land Registry sales and only returns results where at least 3 sales exist.
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="mb-2 text-sm font-medium text-zinc-800">Search postcode</div>
                <form method="GET" action="{{ route('property.search') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        id="home-postcode"
                        name="postcode"
                        type="text"
                        value="{{ old('postcode', request('postcode', '')) }}"
                        placeholder="Search postcode (e.g. SW7 5PH)"
                        class="flex-1 rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-zinc-900"
                    />

                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-5 py-2 text-sm text-white transition hover:bg-black"
                    >
                        Search
                    </button>
                </form>

                <div class="mt-2 text-xs text-zinc-500">
                    Jump straight to full property data for any postcode in England &amp; Wales
                </div>
            </div>
        </div>
    </section>

    @php
        $formatCompactCount = static function (int $value): string {
            if ($value >= 1000000) {
                return rtrim(rtrim(number_format($value / 1000000, 1), '0'), '.').'M';
            }

            if ($value >= 1000) {
                return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.').'K';
            }

            return (string) $value;
        };

        $homepageStatCards = [
            [
                'value' => $formatCompactCount((int) ($stats['property_records'] ?? 0)),
                'label' => 'Property sales',
                'icon' => 'database',
                'change' => '↑ 184k this year',
                'tone' => 'positive',
            ],
            [
                'value' => $formatCompactCount((int) ($stats['epc_count'] ?? 0)),
                'label' => 'EPC certificates',
                'icon' => 'file-search',
                'change' => '↑ 412k this year',
                'tone' => 'positive',
            ],
            [
                'value' => '&pound;'.number_format((int) ($stats['uk_avg_price'] ?? 0)),
                'label' => 'Average House Price',
                'icon' => 'home',
                'change' => '↑ 4.2% YoY',
                'tone' => 'positive',
            ],
            [
                'label' => 'Average UK rent',
                'value' => '&pound;'.number_format((int) ($stats['uk_avg_rent'] ?? 0)),
                'icon' => 'key',
                'change' => '↑ 3.1% YoY',
                'tone' => 'positive',
            ],
            [
                'label' => 'Bank Rate',
                'value' => number_format((float) ($stats['bank_rate'] ?? 0), 2).'%',
                'icon' => 'percent',
                'change' => '↓ 1.50pp from peak',
                'tone' => 'positive',
            ],
        ];
    @endphp
    {{-- TODO: Replace homepage stat card change text with real cached/calculated movement data. --}}

    {{-- Live Stats Section --}}
    <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach($homepageStatCards as $card)
            <x-home.stat-card
                :value="$card['value']"
                :label="$card['label']"
                :icon="$card['icon']"
                :change="$card['change']"
                :tone="$card['tone']"
            />
        @endforeach
    </section>

    {{-- Property Stress Index --}}
    <div class="mt-8">
        @include('partials.stress-score-panel', ['totalStress' => $totalStress ?? null, 'isSticky' => false, 'showDashboardLink' => true])
    </div>

    <section class="mt-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex h-full flex-col gap-3">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">UK Housing Market Snapshot</h2>
                        <p class="mt-1 text-sm text-zinc-600">Latest complete Land Registry quarter vs previous quarter</p>
                    </div>

                    @php
                    $transactionChange = (float) ($homepageMarketMovements['transaction_change_percent'] ?? -34.1);
                    $priceChange = (float) ($homepageMarketMovements['median_price_change_percent'] ?? -0.2);
                    $totalCounties = (int) ($homepageMarketMovements['total_counties'] ?? 112);
                    $risingPriceCounties = (int) ($homepageMarketMovements['rising_price_counties'] ?? 18);
                    $decliningCounties = (int) ($homepageMarketMovements['declining_counties'] ?? 112);
                    $risingPriceTrend = $totalCounties > 0 ? ($risingPriceCounties / $totalCounties) * 100 : 0;
                    $fallingSalesPercent = $totalCounties > 0 ? ($decliningCounties / $totalCounties) * 100 : 0;
                    $decliningSalesTrend = -$fallingSalesPercent;
                    $condition = marketCondition($transactionChange, $priceChange, $fallingSalesPercent);
                    $labels = [
                        'transactions' => 'Demand weakening',
                        'price' => 'Price growth stalling',
                        'rising' => 'Limited market breadth',
                        'falling' => 'Liquidity falling',
                    ];
                    $colorTextClasses = [
                        'red' => 'text-red-600',
                        'yellow' => 'text-yellow-600',
                        'green' => 'text-green-600',
                        'gray' => 'text-zinc-600',
                    ];
                    $conditionClasses = [
                        'red' => 'bg-orange-50 text-orange-800',
                        'yellow' => 'bg-yellow-50 text-yellow-700',
                        'green' => 'bg-lime-50 text-lime-700',
                        'gray' => 'bg-zinc-100 text-zinc-700',
                    ];
                    $transactionColor = marketColor($transactionChange, 'transactions');
                    $priceColor = marketColor($priceChange, 'price');
                    $risingColor = marketColor($risingPriceTrend, 'rising');
                    $fallingColor = marketColor($fallingSalesPercent, 'falling');
                    $risingBreadthTone = 'warning';

                    if ($risingPriceTrend >= 60) {
                        $risingBreadthTone = 'positive';
                    } elseif ($risingPriceTrend >= 40) {
                        $risingBreadthTone = 'warning';
                    } else {
                        $risingBreadthTone = 'warning';
                    }

                    $snapshotCards = [
                        [
                            'value' => number_format($transactionChange, 1).'%',
                            'label' => 'Transactions',
                            'detail' => $labels['transactions'],
                            'tone' => $transactionColor === 'red' ? 'negative' : 'neutral',
                            'icon' => 'trend-down',
                        ],
                        [
                            'value' => number_format($priceChange, 1).'%',
                            'label' => 'Median price',
                            'detail' => $labels['price'],
                            'tone' => $priceColor === 'red' ? 'negative' : 'neutral',
                            'icon' => 'home',
                        ],
                        [
                            'value' => number_format($risingPriceCounties).' / '.number_format($totalCounties),
                            'label' => 'Counties with rising prices',
                            'detail' => $totalCounties > 0 ? number_format($risingPriceTrend, 0).'% market breadth' : 'No counties available',
                            'tone' => $risingBreadthTone,
                            'icon' => 'trend-up',
                        ],
                        [
                            'value' => number_format($decliningCounties).' / '.number_format($totalCounties),
                            'label' => 'Counties with falling sales',
                            'detail' => $totalCounties > 0 ? number_format($fallingSalesPercent, 0).'% liquidity falling' : 'No counties available',
                            'tone' => $fallingColor === 'red' ? 'negative' : 'neutral',
                            'icon' => 'alert',
                        ],
                    ];
                    @endphp

                    <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-sm font-semibold {{ $conditionClasses[$condition['color']] ?? $conditionClasses['gray'] }}">
                        {{ $condition['label'] }} Market
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($snapshotCards as $card)
                        <x-home.snapshot-card
                            :value="$card['value']"
                            :label="$card['label']"
                            :detail="$card['detail']"
                            :tone="$card['tone']"
                            :icon="$card['icon']"
                        />
                    @endforeach
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    <a href="{{ route('insights.dashboard') }}"
                       class="inline-flex items-center gap-2 text-sm font-medium text-lime-700 hover:underline sm:ml-auto">
                        View Market Insights
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Explore panels --}}
    <section class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        <a href="{{ Route::has('property.home') ? route('property.home') : url('/property') }}"
           class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900">Review Property Data</h2>
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="h-6 w-6 text-zinc-500 group-hover:text-lime-600 transition">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                </svg>
            </div>
            <p class="mt-2 text-sm text-zinc-700">Drill into transactions by postcode, street or any area.  Now you can browse properties in a map. Yearly trends &amp; quick summaries.</p>
            <div class="mt-auto inline-flex items-center pt-4 text-sm font-medium text-lime-700 group-hover:underline">Open Property Dashboard
            </div>
        </a>

        <a href="{{ Route::has('epc.home') ? route('epc.home') : url('/epc') }}"
           class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900">Find an EPC report</h2>
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="h-6 w-6 text-zinc-500 group-hover:text-lime-600 transition">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <p class="mt-2 text-sm text-zinc-700">EPC report details for England, Wales and Scotland.  Dashboard contains some information not available from the Land Registry</p>
            <div class="mt-auto inline-flex items-center pt-4 text-sm font-medium text-lime-700 group-hover:underline">Open EPC Dashboard
            </div>
        </a>

        <a href="{{ Route::has('deprivation.index') ? route('deprivation.index') : url('/deprivation') }}"
           class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900">View Deprivation by Area</h2>
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="h-6 w-6 text-zinc-500 group-hover:text-lime-600 transition">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
            </div>
            <p class="mt-2 text-sm text-zinc-700">Explore the Deprivation indexes. Search by postcode and see domain breakdowns.  Scotland, England, Wales and Northern Ireland.</p>
            <div class="mt-auto inline-flex items-center pt-4 text-sm font-medium text-lime-700 group-hover:underline">Open Deprivation Dashboard
            </div>
        </a>

        <a href="{{ route('insights.swap-rates') }}"
           class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900">Understand UK Swap Rates</h2>
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="h-6 w-6 text-zinc-500 group-hover:text-lime-600 transition">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M3.75 15.75 9 10.5l3.75 3.75L20.25 6.75M16.5 6.75h3.75V10.5" />
                </svg>
            </div>
            <p class="mt-2 text-sm text-zinc-700">View current UK swap rates and follow the wholesale pricing moves that influence fixed mortgage costs.</p>
            <div class="mt-auto inline-flex items-center pt-4 text-sm font-medium text-lime-700 group-hover:underline">Open Swap Rates
            </div>
        </a>

        <a href="{{ route('insights.index') }}"
           class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900">Property Signals Worth Watching</h2>
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="h-6 w-6 text-zinc-500 group-hover:text-lime-600 transition">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M3.75 15.75 8.25 11.25l3 3 5.25-6.75 3.75 3.75" />
                </svg>
            </div>
            <p class="mt-2 text-sm text-zinc-700">Browse the latest county-level property signals and market insights without crowding the homepage with specialist detail.</p>
            <div class="mt-auto inline-flex items-center pt-4 text-sm font-medium text-lime-700 group-hover:underline">Open County Insights
            </div>
        </a>

        <a href="{{ url('/insights/crime') }}"
           class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white p-6 shadow-sm transition hover:shadow-md">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-zinc-900">View Crime Insights</h2>
                <svg xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke-width="1.5"
                     stroke="currentColor"
                     class="h-6 w-6 text-zinc-500 group-hover:text-lime-600 transition">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-1.5 0h12a1.5 1.5 0 0 1 1.5 1.5v7.5a1.5 1.5 0 0 1-1.5 1.5h-12A1.5 1.5 0 0 1 4.5 19.5V12a1.5 1.5 0 0 1 1.5-1.5Z" />
                </svg>
            </div>
            <p class="mt-2 text-sm text-zinc-700">Open the crime dashboard for national and local crime trends, recent movement, and area-level context alongside the property research.</p>
            <div class="mt-auto inline-flex items-center pt-4 text-sm font-medium text-lime-700 group-hover:underline">Open Crime Insights
            </div>
        </a>

    </section>

    {{-- Blog Section --}}
    @if($posts->count() > 0)
    <section class="mt-12">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-zinc-900">Latest Insights</h2>
                <p class="text-sm text-zinc-500 mt-1">Analysis and commentary on the UK property market</p>
            </div>
            <a href="{{ url('/blog') }}" class="hidden sm:inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 hover:border-zinc-300">
                View all posts
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Other posts (smaller, stacked) --}}
            <div class="lg:col-span-12 grid grid-cols-1 sm:grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach ($posts as $post)
                    <a href="/blog/{{ $post->slug }}"
                       class="group flex gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-all duration-300 hover:shadow-md hover:border-zinc-300">
                        <div class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-zinc-100">
                            <img src="{{ $post->featuredImageUrl('small') }}"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                 alt="{{ $post->title }}">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs text-zinc-500 mb-1">
                                {{ $post->date->format('M j, Y') }}
                            </div>
                            <h3 class="font-semibold text-zinc-900 line-clamp-2 group-hover:text-lime-700 transition-colors">
                                {{ $post->title }}
                            </h3>
                            <p class="mt-1 text-sm text-zinc-500 line-clamp-2 sm:block lg:block">
                                {{ $post->summary }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Mobile "View all" link --}}
        <div class="mt-6 sm:hidden">
            <a href="{{ url('/blog') }}" class="block w-full text-center rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50">
                View all posts
            </a>
        </div>
    </section>
    @endif

</div>
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('home-street-search');
        const suggestionsBox = document.getElementById('home-street-suggestions');

        if (!input || !suggestionsBox) {
            return;
        }

        let streets = [];

        fetch('{{ asset('data/property_streets.json') }}')
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Street index unavailable');
                }

                return response.json();
            })
            .then(function (payload) {
                if (Array.isArray(payload)) {
                    streets = payload;
                }
            })
            .catch(function () {
                streets = [];
            });

        const hideSuggestions = function () {
            suggestionsBox.classList.add('hidden');
            suggestionsBox.innerHTML = '';
        };

        const renderSuggestions = function (query) {
            const normalizedQuery = query.trim().toLowerCase();

            suggestionsBox.innerHTML = '';

            if (normalizedQuery.length < 2) {
                hideSuggestions();

                return;
            }

            const matches = streets
                .filter(function (item) {
                    const search = item && item.search ? String(item.search) : '';

                    return search.includes(normalizedQuery);
                })
                .slice(0, 12);

            if (matches.length === 0) {
                hideSuggestions();

                return;
            }

            matches.forEach(function (item) {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'block w-full px-4 py-2 text-left text-zinc-700 hover:bg-zinc-100';
                option.textContent = item.label || '';
                option.addEventListener('click', function () {
                    if (item.path) {
                        window.location.href = item.path;
                    }
                });
                suggestionsBox.appendChild(option);
            });

            suggestionsBox.classList.remove('hidden');
        };

        input.addEventListener('input', function () {
            renderSuggestions(this.value);
        });

        document.addEventListener('click', function (event) {
            if (!suggestionsBox.contains(event.target) && event.target !== input) {
                hideSuggestions();
            }
        });
    });
</script>
@endpush
@endsection
