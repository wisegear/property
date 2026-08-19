@extends('layouts.app')

@section('content')
    {{-- Hero --}}
    <section class="relative z-0 -mx-6 -mt-6 overflow-hidden bg-white py-8 shadow-[0_10px_24px_-20px_rgba(15,23,42,0.18)] md:py-9">
        <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[minmax(0,1fr)_minmax(280px,0.42fr)] md:gap-8">
            <div class="max-w-4xl">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex items-center gap-2 text-xs font-medium text-zinc-600">
                        <span class="h-2 w-2 rounded-full bg-lime-600"></span>
                        Independent UK property data
                    </div>
                    @auth
                        @if (Auth::id() === 1)
                            <div class="inline-flex items-center gap-2 rounded border border-emerald-200 bg-white px-3 py-1 text-xs font-medium text-emerald-800">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400/70"></span>
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                </span>
                                Admin Online
                            </div>
                        @endif
                    @endauth
                </div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-950 md:text-4xl">
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

            <div class="hidden min-w-0 justify-end md:flex">
                <a
                    href="https://apps.apple.com/gb/app/id6794914030"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Download PropertyResearch on the App Store (opens in a new tab)"
                    class="group block max-w-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-4"
                >
                    <img
                        src="{{ asset('/propertyresearch-app.png') }}"
                        alt="PropertyResearch iPhone app"
                        width="1536"
                        height="1024"
                        class="h-44 w-auto max-w-full object-contain transition-transform duration-200 group-hover:scale-[1.02] lg:h-52"
                    >
                </a>
            </div>
        </div>
    </section>

<div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 pt-8 pb-8 md:pb-10">
    <section class="overflow-visible rounded-sm border border-lime-200/70 bg-lime-50/50 p-5 md:p-6">
        <div class="grid gap-5 md:grid-cols-2 md:items-start lg:grid-cols-[22fr_40fr_38fr]">
            <div class="md:col-span-2 lg:col-span-1 lg:self-center">
                <h2 class="text-lg font-bold text-zinc-900">Search properties</h2>
                <p class="mt-1 text-sm text-zinc-600">Search properties across England and Wales</p>
            </div>

            <div>
                <label for="home-street-search" class="mb-2 block text-xs font-semibold text-zinc-700">Street search</label>
                <div class="relative">
                    <input
                        id="home-street-search"
                        type="text"
                        autocomplete="off"
                        placeholder="Street, place or postcode district"
                        class="w-full rounded border border-lime-200 bg-white px-4 py-2.5 text-sm focus:border-lime-600 focus:outline-none focus:ring-2 focus:ring-lime-200"
                    />
                    <div
                        id="home-street-suggestions"
                        class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-y-auto rounded border border-zinc-200 bg-white text-sm shadow-lg">
                    </div>
                </div>

                <p class="mt-2 text-xs text-zinc-500">Matches streets with at least 3 recorded sales. Add a place or postcode district to narrow common names.</p>
            </div>

            <div class="lg:pl-8">
                <label for="home-postcode" class="mb-2 block text-xs font-semibold text-zinc-700">Postcode search</label>
                <form method="GET" action="{{ route('property.search') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <input
                        id="home-postcode"
                        name="postcode"
                        type="text"
                        value="{{ old('postcode', request('postcode', '')) }}"
                        placeholder="E.g. SW7 5PH"
                        class="min-w-0 flex-1 rounded border border-lime-200 bg-white px-4 py-2.5 text-sm focus:border-lime-600 focus:outline-none focus:ring-2 focus:ring-lime-200 lg:w-64 lg:flex-none"
                    />

                    <button
                        type="submit"
                        class="rounded bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2"
                    >
                        Search
                    </button>
                </form>

                <p class="mt-2 text-xs text-zinc-500">Open the full property record for an England or Wales postcode.</p>
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
            ],
            [
                'value' => $formatCompactCount((int) ($stats['epc_count'] ?? 0)),
                'label' => 'EPC certificates',
                'icon' => 'file-search',
            ],
            [
                'value' => '&pound;'.number_format((int) ($stats['uk_avg_price'] ?? 0)),
                'label' => 'Average House Price',
                'icon' => 'home',
            ],
            [
                'label' => 'Average UK rent',
                'value' => '&pound;'.number_format((int) ($stats['uk_avg_rent'] ?? 0)),
                'icon' => 'key',
            ],
            [
                'label' => 'Bank Rate',
                'value' => number_format((float) ($stats['bank_rate'] ?? 0), 2).'%',
                'icon' => 'percent',
            ],
        ];
    @endphp

    {{-- Live Stats Section --}}
    <section class="grid grid-cols-1 overflow-hidden rounded-sm border border-slate-200 bg-white divide-y divide-slate-200 lg:grid-cols-5 lg:divide-x lg:divide-y-0">
        @foreach($homepageStatCards as $card)
            <x-home.stat-card
                :value="$card['value']"
                :label="$card['label']"
                :icon="$card['icon']"
            />
        @endforeach
    </section>

    {{-- Property Stress Index --}}
    <div>
        @include('partials.stress-score-panel', ['totalStress' => $totalStress ?? null, 'isSticky' => false, 'showDashboardLink' => true])
    </div>

    <section>
        <div class="rounded-sm border border-zinc-200 bg-white p-6">
            <div class="flex h-full flex-col gap-3">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">UK Housing Market Snapshot</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <p class="text-sm text-zinc-600">Latest complete Land Registry quarter vs previous quarter</p>
                            <a href="{{ route('insights.dashboard') }}"
                               class="inline-flex items-center gap-2 text-sm font-medium text-lime-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                                View Market Insights
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
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
                            'gauge_value' => $transactionChange,
                            'gauge_variant' => 'dashboard-dual',
                            'invert_gauge' => false,
                        ],
                        [
                            'value' => number_format($priceChange, 1).'%',
                            'label' => 'Median price',
                            'detail' => $labels['price'],
                            'tone' => $priceColor === 'red' ? 'negative' : 'neutral',
                            'icon' => 'home',
                            'gauge_value' => $priceChange,
                            'gauge_variant' => 'dashboard-dual',
                            'invert_gauge' => false,
                        ],
                        [
                            'value' => number_format($risingPriceCounties).' / '.number_format($totalCounties),
                            'label' => 'Counties with rising prices',
                            'detail' => $totalCounties > 0 ? number_format($risingPriceTrend, 0).'% market breadth' : 'No counties available',
                            'tone' => $risingBreadthTone,
                            'icon' => 'trend-up',
                            'gauge_value' => ($risingPriceTrend * 2) - 100,
                            'gauge_variant' => 'market-status',
                            'invert_gauge' => false,
                        ],
                        [
                            'value' => number_format($decliningCounties).' / '.number_format($totalCounties),
                            'label' => 'Counties with falling sales',
                            'detail' => $totalCounties > 0 ? number_format($fallingSalesPercent, 0).'% liquidity falling' : 'No counties available',
                            'tone' => $fallingColor === 'red' ? 'negative' : 'neutral',
                            'icon' => 'alert',
                            'gauge_value' => ($fallingSalesPercent * 2) - 100,
                            'gauge_variant' => 'market-status',
                            'invert_gauge' => true,
                        ],
                    ];
                    @endphp

                    <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-sm font-semibold {{ $conditionClasses[$condition['color']] ?? $conditionClasses['gray'] }}">
                        {{ $condition['label'] }} Market
                    </span>
                </div>

                <div class="grid grid-cols-1 overflow-hidden rounded-sm border border-slate-200 bg-white divide-y divide-slate-200 xl:grid-cols-4 xl:divide-x xl:divide-y-0">
                    @foreach ($snapshotCards as $card)
                        <x-home.snapshot-card
                            :value="$card['value']"
                            :label="$card['label']"
                            :detail="$card['detail']"
                            :tone="$card['tone']"
                            :icon="$card['icon']"
                            :gauge-value="$card['gauge_value']"
                            :gauge-variant="$card['gauge_variant']"
                            :invert-gauge="$card['invert_gauge']"
                        />
                    @endforeach
                </div>

            </div>
        </div>
    </section>

    {{-- Explore PropertyResearch --}}
    <section class="overflow-hidden rounded-sm border border-zinc-200 bg-white">
        <div class="flex flex-col gap-2 border-b border-zinc-200 bg-zinc-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-6">
            <div>
                <h2 class="text-xl font-bold text-zinc-900">Explore PropertyResearch</h2>
                <p class="mt-1 text-sm text-zinc-600">Free UK property data, market indicators and local research.</p>
            </div>
            <a href="{{ route('insights.dashboard') }}" class="text-sm font-semibold text-lime-700 hover:underline">View current market insights →</a>
        </div>

        <div class="grid divide-y divide-zinc-200 md:grid-cols-3 md:divide-x md:divide-y-0">
            <div class="relative overflow-hidden bg-lime-50/40 p-5 md:p-6">
                <div class="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full bg-lime-100/70" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-lime-700">High-end market research</p>
                    <h3 class="mt-2 font-bold text-zinc-900">High Value Property</h3>
                    <p class="mt-1 text-xs leading-5 text-zinc-600">Explore the top 10% of residential transactions and see where expensive homes are selling.</p>

                    <div class="mt-4 grid grid-cols-2 border-y border-lime-200/70 text-xs text-zinc-600">
                        <span class="border-b border-r border-lime-200/70 py-2.5 pr-2">90th percentile</span>
                        <span class="border-b border-lime-200/70 py-2.5 pl-3">Top sales</span>
                        <span class="border-r border-lime-200/70 py-2.5 pr-2">Hotspot map</span>
                        <span class="py-2.5 pl-3">£1m+ market</span>
                    </div>

                    <a href="{{ route('top-sales.index') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-lime-700 hover:underline">
                        Explore high-value property
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>

            <div class="p-5 md:p-6">
                <h3 class="font-bold text-zinc-900">Swap Rates</h3>
                <p class="mt-1 text-xs leading-5 text-zinc-500">Wholesale market rates influencing fixed mortgage pricing.</p>
                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-y border-zinc-100 py-3">
                    @foreach(collect($homepageSwapRates['rates'] ?? [])->take(3) as $rate)
                        @php
                            $dailyChange = (float) ($rate['daily_change'] ?? 0);
                            $rateColorClass = match (true) {
                                $dailyChange < 0 => 'text-emerald-700',
                                $dailyChange > 0 => 'text-rose-700',
                                default => 'text-zinc-900',
                            };
                        @endphp
                        <div><span class="block text-[11px] text-zinc-500">{{ $rate['label'] }}</span><strong class="text-lg {{ $rateColorClass }}">{{ number_format((float) $rate['rate'], 2) }}%</strong></div>
                    @endforeach
                </div>
                <div class="mt-4 grid gap-2.5 text-sm">
                    <a href="{{ route('insights.swap-rates') }}" class="font-medium text-zinc-700 hover:text-lime-700">UK swap rates</a>
                    <a href="{{ route('economic.dashboard') }}" class="font-medium text-zinc-700 hover:text-lime-700">Economic dashboard</a>
                </div>
            </div>

            <div class="relative overflow-hidden bg-lime-50/40 p-5 md:p-6">
                <div class="absolute right-0 top-0 h-24 w-24 translate-x-8 -translate-y-8 rounded-full bg-lime-100/70" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-lime-700">New property research</p>
                    <h3 class="mt-2 font-bold text-zinc-900">Property Monthly Snapshot</h3>
                    <p class="mt-1 text-xs leading-5 text-zinc-600">See what the latest Land Registry month reveals about sales, prices and market activity.</p>

                    <div class="mt-4 grid grid-cols-2 border-y border-lime-200/70 text-xs text-zinc-600">
                        <span class="border-b border-r border-lime-200/70 py-2.5 pr-2">Monthly comparisons</span>
                        <span class="border-b border-lime-200/70 py-2.5 pl-3">Housing mix</span>
                        <span class="border-r border-lime-200/70 py-2.5 pr-2">Regional map</span>
                        <span class="py-2.5 pl-3">Notable sales</span>
                    </div>

                    <a href="{{ route('property.monthly-snapshot') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-lime-700 hover:underline">
                        Explore the latest snapshot
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Blog Section --}}
    @if($posts->count() > 0)
    <section class="border-t border-zinc-200 pt-7">
        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-zinc-900">Latest Insights</h2>
                <p class="mt-1 text-sm text-zinc-500">Analysis and commentary on the UK property market</p>
            </div>
            <a href="{{ url('/blog') }}" class="hidden items-center gap-2 text-sm font-semibold text-lime-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2 sm:inline-flex">
                View all posts
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </a>
        </div>

        <div class="grid grid-cols-1 overflow-hidden border-y border-zinc-200 bg-white lg:grid-cols-2 lg:divide-x lg:divide-zinc-200">
            @foreach($posts->chunk((int) ceil($posts->count() / 2)) as $columnPosts)
                <div class="grid grid-cols-1 divide-y divide-zinc-200">
                    @foreach($columnPosts as $post)
                    <a href="/blog/{{ $post->slug }}"
                       class="group grid min-h-32 grid-cols-[minmax(0,1fr)_auto] items-center gap-5 p-5 transition-colors duration-200 hover:bg-zinc-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-lime-600">
                        <div class="min-w-0">
                            <div class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-zinc-500">
                                {{ $post->date->format('M j, Y') }}
                            </div>
                            <h3 class="line-clamp-2 text-base font-bold leading-5 text-zinc-900 transition-colors group-hover:text-lime-700 sm:text-[1.05rem]">
                                {{ $post->title }}
                            </h3>
                            <p class="mt-2 line-clamp-2 text-sm leading-5 text-zinc-500">
                                {{ $post->summary }}
                            </p>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-zinc-400 transition-transform group-hover:translate-x-0.5 group-hover:text-lime-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </a>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Mobile "View all" link --}}
        <div class="mt-4 sm:hidden">
            <a href="{{ url('/blog') }}" class="block text-sm font-semibold text-lime-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                View all posts →
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

        const streetMatchRank = function (street, normalizedQuery) {
            if (street === normalizedQuery) {
                return 0;
            }

            if (street.startsWith(normalizedQuery)) {
                return 1;
            }

            if (street.includes(normalizedQuery)) {
                return 2;
            }

            return 3;
        };

        const formatStreetSuggestionLabel = function (item) {
            const place = item && item.place ? String(item.place).trim() : '';
            const outcode = item && item.outcode ? String(item.outcode).trim() : '';
            const salesCount = Number(item && item.sales_count ? item.sales_count : 0);
            const locationLabel = place !== '' && place.toLowerCase() !== outcode.toLowerCase()
                ? place + ', ' + outcode
                : outcode;

            return String(item.street || '') + ', ' + locationLabel + ' \u2014 ' + salesCount.toLocaleString('en-GB') + ' sales';
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
                    const street = item && item.street ? String(item.street).toLowerCase() : '';
                    const place = item && item.place ? String(item.place).toLowerCase() : '';
                    const outcode = item && item.outcode ? String(item.outcode).toLowerCase() : '';
                    const haystack = [street, place, outcode].filter(Boolean).join(' ');

                    return haystack.includes(normalizedQuery);
                })
                .sort(function (left, right) {
                    const leftStreet = String(left.street || '').toLowerCase();
                    const rightStreet = String(right.street || '').toLowerCase();
                    const rankDiff = streetMatchRank(leftStreet, normalizedQuery) - streetMatchRank(rightStreet, normalizedQuery);

                    if (rankDiff !== 0) {
                        return rankDiff;
                    }

                    const salesDiff = Number(right.sales_count || 0) - Number(left.sales_count || 0);

                    if (salesDiff !== 0) {
                        return salesDiff;
                    }

                    const placeDiff = String(left.place || '').localeCompare(String(right.place || ''));

                    if (placeDiff !== 0) {
                        return placeDiff;
                    }

                    return String(left.outcode || '').localeCompare(String(right.outcode || ''));
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
                option.textContent = formatStreetSuggestionLabel(item);
                option.addEventListener('click', function () {
                    if (item.url) {
                        window.location.href = item.url;
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
