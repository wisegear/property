@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 md:py-10">

    {{-- Hero --}}
    <section class="relative z-0 overflow-hidden rounded-lg border border-zinc-200 bg-white p-8 shadow-sm flex flex-col md:flex-row justify-between items-center">
        @include('partials.hero-background')
        <div class="pointer-events-none absolute -left-20 -top-16 h-64 w-64 rounded-full bg-lime-100/60 blur-3xl -z-10"></div>
        <div class="max-w-5xl relative z-10">
            <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 bg-white/70 px-3 py-1 text-xs text-zinc-700 shadow-sm">
                <span class="h-2 w-2 rounded-lg bg-lime-500"></span>
                Fresh, independent property data
            </div>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">
                Property Research > clear, credible & fast datasets
            </h1>
            <p class="mt-3 text-md leading-7 text-zinc-500">
                Explore sales, repossessions and market signals across England &amp; Wales (some Scotland & NI). Built for clarity, speed and repeatable 
                analysis.  Best on larger screens, some tables are too wide for mobiles. Best of all? <span class="text-lime-900">All free!  No fees or subscriptions.</span>
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-zinc-600">
                <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/70 px-3 py-1">
                    <span class="h-2 w-2 rounded-lg bg-lime-500"></span>
                    Latest data: Jan 2026
                </span>
                <span class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 bg-white/70 px-3 py-1">
                    <span class="h-2 w-2 rounded-lg bg-zinc-400"></span>
                    Next update: 31st March 2026
                </span>
            </div>
        </div>
        <div class="mt-6 md:mt-0 md:ml-8 flex-shrink-0">
            <img src="{{ asset('/assets/images/site/home.jpg') }}" alt="Property Research" class="w-88 h-auto">
        </div>
    </section>

    {{-- Live Stats Section --}}
    <section class="mt-8 grid grid-cols-2 md:grid-cols-6 gap-4" x-data="{
        shown: false,
        propertyRecords: 0,
        ukAvgPrice: 0,
        ukAvgRent: 0,
        bankRate: 0,
        inflationRate: 0,
        epcCount: 0,
        animateValue(start, end, key, duration) {
            const range = end - start;
            const startTime = performance.now();
            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOut = 1 - Math.pow(1 - progress, 3);
                this[key] = Math.floor(start + range * easeOut);
                if (progress < 1) requestAnimationFrame(animate);
            };
            requestAnimationFrame(animate);
        }
    }" x-init="
        setTimeout(() => {
            shown = true;
            animateValue(0, {{ $stats['property_records'] ?? 0 }}, 'propertyRecords', 2000);
            animateValue(0, {{ $stats['uk_avg_price'] ?? 0 }}, 'ukAvgPrice', 2000);
            animateValue(0, {{ $stats['uk_avg_rent'] ?? 0 }}, 'ukAvgRent', 2000);
            animateValue(0, {{ ($stats['bank_rate'] ?? 0) * 100 }}, 'bankRate', 1500);
            animateValue(0, {{ ($stats['inflation_rate'] ?? 0) * 100 }}, 'inflationRate', 1500);
            animateValue(0, {{ $stats['epc_count'] ?? 0 }}, 'epcCount', 2000);
        }, 300);
    ">
        {{-- Property Records --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-500"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="flex items-center min-w-0">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Property Records</p>
                    <p class="text-base sm:text-base font-bold text-zinc-900 leading-tight tracking-tight tabular-nums break-words" x-text="propertyRecords.toLocaleString()">0</p>
                </div>
            </div>
        </div>

        {{-- EPC Records --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-500 delay-100"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="flex items-center min-w-0">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">EPC Records</p>
                    <p class="text-base sm:text-base font-bold text-zinc-900 leading-tight tracking-tight tabular-nums break-words" x-text="epcCount.toLocaleString()">0</p>
                </div>
            </div>
        </div>

        {{-- UK Average Price --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-500 delay-200"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="flex items-center min-w-0">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">UK House Price</p>
                    <p class="text-base sm:text-base font-bold text-zinc-900 leading-tight tracking-tight tabular-nums break-words">&pound;<span x-text="ukAvgPrice.toLocaleString()">0</span></p>
                </div>
            </div>
        </div>

        {{-- UK Average Rent --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-500 delay-300"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="flex items-center min-w-0">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Average UK Rent</p>
                    <p class="text-base sm:text-base font-bold text-zinc-900 leading-tight tracking-tight tabular-nums break-words">&pound;<span x-text="ukAvgRent.toLocaleString()">0</span></p>
                </div>
            </div>
        </div>

        {{-- Bank Rate --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-500 delay-400"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="flex items-center min-w-0">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Interest Rate</p>
                    <p class="text-base sm:text-base font-bold text-zinc-900 leading-tight tracking-tight tabular-nums break-words"><span x-text="(bankRate / 100).toFixed(2)">0</span>%</p>
                </div>
            </div>
        </div>

        {{-- Inflation --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-500 delay-500"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            <div class="flex items-center min-w-0">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Inflation (CPIH)</p>
                    <p class="text-base sm:text-base font-bold text-zinc-900 leading-tight tracking-tight tabular-nums break-words"><span x-text="(inflationRate / 100).toFixed(2)">0</span>%</p>
                </div>
            </div>
        </div>

    </section>

    {{-- Property Stress Index --}}
    <div class="mt-8">
        @include('partials.stress-score-panel', ['totalStress' => $totalStress ?? null, 'isSticky' => false, 'showDashboardLink' => true])
    </div>

    <section class="mt-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex h-full flex-col gap-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">UK Housing Market Snapshot <span class="text-xs font-normal text-zinc-500">(Last 2 quarters)</span></h2>
                        <p class="mt-1 text-sm text-zinc-600">
                            The latest Land Registry window indicates a broad market slowdown, with transaction volumes declining across all counties and only limited price growth remaining.
                        </p>
                    </div>
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
                        'red' => 'bg-red-100 text-red-700',
                        'yellow' => 'bg-yellow-100 text-yellow-700',
                        'green' => 'bg-green-100 text-green-700',
                        'gray' => 'bg-zinc-100 text-zinc-700',
                    ];
                    $transactionColor = marketColor($transactionChange, 'transactions');
                    $priceColor = marketColor($priceChange, 'price');
                    $risingColor = marketColor($risingPriceTrend, 'rising');
                    $fallingColor = marketColor($fallingSalesPercent, 'falling');
                    $stats = [
                        [
                            'title' => 'Change in Transactions',
                            'value' => $transactionChange,
                            'formatted' => number_format($transactionChange, 1).'%',
                            'titleText' => number_format($transactionChange, 1).'%',
                            'label' => $labels['transactions'],
                            'color' => $transactionColor,
                            'gaugeValue' => $transactionChange,
                            'text' => number_format($transactionChange, 1).'%',
                        ],
                        [
                            'title' => 'Median Price % Change',
                            'value' => $priceChange,
                            'formatted' => number_format($priceChange, 1).'%',
                            'titleText' => number_format($priceChange, 1).'%',
                            'label' => $labels['price'],
                            'color' => $priceColor,
                            'gaugeValue' => $priceChange,
                            'text' => number_format($priceChange, 1).'%',
                        ],
                        [
                            'title' => 'Counties with Rising Prices',
                            'value' => $risingPriceTrend,
                            'formatted' => $totalCounties > 0 ? number_format($risingPriceTrend, 0).'% of counties' : 'No counties available',
                            'label' => $labels['rising'],
                            'color' => $risingColor,
                            'gaugeValue' => $risingPriceTrend,
                            'text' => number_format($risingPriceCounties).' / '.number_format($totalCounties),
                            'suffix' => $totalCounties > 0 ? '('.number_format($risingPriceTrend, 0).'%)' : null,
                        ],
                        [
                            'title' => 'Counties with Falling Sales',
                            'value' => $fallingSalesPercent,
                            'formatted' => $totalCounties > 0 ? number_format($fallingSalesPercent, 0).'% of counties' : 'No counties available',
                            'label' => $labels['falling'],
                            'color' => $fallingColor,
                            'gaugeValue' => $decliningSalesTrend,
                            'text' => number_format($decliningCounties).' / '.number_format($totalCounties),
                        ],
                    ];
                @endphp

                <div class="mb-1 flex items-center gap-2">
                    <span class="text-sm text-zinc-500">Market Condition:</span>
                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $conditionClasses[$condition['color']] ?? $conditionClasses['gray'] }}">
                        {{ $condition['label'] }}
                    </span>
                </div>

                <div class="mt-1 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($stats as $stat)
                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-semibold text-zinc-800">{{ $stat['title'] }}</p>
                                @include('partials.trend-gauge', [
                                    'value' => $stat['gaugeValue'],
                                    'title' => $stat['formatted'],
                                    'color' => $stat['color'],
                                    'wrapperClass' => 'ml-0 -mt-2',
                                ])
                            </div>
                            <p class="mt-1 text-lg font-semibold {{ $colorTextClasses[$stat['color']] ?? $colorTextClasses['gray'] }}">
                                {{ $stat['text'] }}
                                @isset($stat['suffix'])
                                    <span class="text-sm font-medium text-zinc-500">{{ $stat['suffix'] }}</span>
                                @endisset
                            </p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $stat['label'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <p class="font-semibold text-zinc-900">Top Counties with Falling Sales:</p>
                        <div class="flex flex-wrap gap-6">
                            @forelse (($homepageMarketMovements['top_declining_counties'] ?? collect()) as $county)
                                <div class="flex items-center gap-1">
                                    <span class="text-zinc-700">{{ \Illuminate\Support\Str::title(strtolower($county['county'])) }}</span>
                                    <span class="font-semibold text-red-600">▼ {{ number_format((float) $county['sales_change_percent'], 1) }}%</span>
                                </div>
                            @empty
                                <span class="text-zinc-500">No counties recorded falling sales in this window.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <p class="font-semibold text-zinc-900">Top Counties with Rising Prices:</p>
                        <div class="flex flex-wrap gap-6">
                            @forelse (($homepageMarketMovements['top_rising_price_counties'] ?? collect()) as $county)
                                <div class="flex items-center gap-1">
                                    <span class="text-zinc-700">{{ \Illuminate\Support\Str::title(strtolower($county['county'])) }}</span>
                                    <span class="font-semibold text-green-600">▲ {{ number_format((float) $county['price_change_percent'], 1) }}%</span>
                                </div>
                            @empty
                                <span class="text-zinc-500">No counties recorded rising prices in this window.</span>
                            @endforelse
                        </div>
                        <a href="{{ route('insights.dashboard') }}"
                           class="inline-flex items-center gap-2 text-sm font-medium text-lime-700 hover:underline sm:ml-auto">
                            View Insights Dashboard
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex h-full flex-col items-center justify-center gap-4 text-center">
                <div class="max-w-3xl">
                    <h2 class="text-lg font-semibold text-zinc-900">Quick postcode search <span class="text-sm text-zinc-500">(England & Wales Only)</span></h2>
                    <p class="mt-1 text-sm text-zinc-600">
                        Jump straight to the Property Search page for a postcode, for example SW7 5PH.
                    </p>
                </div>

                <form method="GET" action="{{ route('property.search') }}" class="w-full max-w-xl">
                    <div class="flex w-full flex-col justify-center gap-3 sm:flex-row">
                        <input
                            id="home-postcode"
                            name="postcode"
                            type="text"
                            value="{{ old('postcode', request('postcode', '')) }}"
                            placeholder="e.g. SW7 5PH"
                            class="w-full min-w-0 rounded-md border border-zinc-300 bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-lime-500 sm:min-w-72"
                        />
                        <button type="submit" class="inner-button whitespace-nowrap">
                            Search postcode
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="flex h-full flex-col gap-4">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-zinc-900">Signals worth watching</h2>
                        <div class="flex flex-wrap gap-2 text-[11px] font-semibold tracking-wide">
                            <span class="inline-flex items-center rounded-full border border-lime-200 bg-lime-50 px-2.5 py-1 text-lime-700">
                                {{ number_format($marketInsightsCount ?? 0) }} live
                            </span>
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-amber-700">
                                {{ $marketInsightSignalCount ?? 9 }} signal types
                            </span>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-zinc-600">
                        Browse price signals, market activity shifts, and longer-term trend signals from rolling 12-month Land Registry windows, with postcode sector pages and historical sales context behind each insight.
                    </p>
                </div>

                <div class="inline-flex items-center gap-2 pt-1 text-sm font-medium text-lime-700 group-hover:underline">
                    <a href="{{ route('insights.index') }}" class="inline-flex items-center gap-2">
                        Open Insights
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
                <h2 class="text-lg font-semibold text-zinc-900">Property Sales</h2>
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
                <h2 class="text-lg font-semibold text-zinc-900">Energy Performance Certificates</h2>
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
                <h2 class="text-lg font-semibold text-zinc-900">Deprivation Indexes</h2>
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
@endsection
