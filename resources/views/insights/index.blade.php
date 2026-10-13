@extends('layouts.app')

@section('title', 'Places worth investigating | PropertyResearch.uk')
@section('meta_description', 'Find unusual local property price and sales changes, understand the evidence, and explore areas worth investigating.')

@section('content')
<section class="-mx-6 -mt-6 border-b border-zinc-200 bg-white py-10">
    <div class="mx-auto max-w-7xl px-4">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Property market research</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">Places worth investigating</h1>
        <p class="mt-4 max-w-2xl text-sm leading-6 text-zinc-600">Find unusual changes in local sale prices and activity. Start with an area you care about, then explore the evidence behind the change.</p>
        <form action="{{ route('insights.search') }}" method="GET" class="mt-6 flex max-w-2xl flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="insight-search" class="block text-sm font-semibold text-zinc-800">Find a postcode area or sector</label>
                <input id="insight-search" name="search" type="search" maxlength="120" value="{{ $search }}" placeholder="For example, B12 or AL6 9" class="mt-2 w-full rounded-sm border border-zinc-300 px-4 py-3 text-sm focus:border-lime-500 focus:ring-lime-500">
            </div>
            <button class="rounded-sm bg-zinc-900 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-700">Find an area</button>
        </form>
        @error('search') <p class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
        @if ($lastRunAt)
            <p class="mt-4 text-xs text-zinc-500">Signals updated {{ $lastRunAt->timezone(config('app.timezone'))->format('d M Y, H:i') }} · Individual comparison dates appear below.</p>
        @endif
    </div>
</section>
<div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8">
    <aside class="rounded-sm border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-zinc-700">
        <p class="font-semibold text-zinc-900">A starting point for research</p>
        <p class="mt-1">Recent Land Registry records can be incomplete, so fewer recorded sales do not prove weaker demand. Median prices also change when the mix of homes sold changes. These findings highlight questions to investigate, rather than forecasts or buying recommendations.</p>
    </aside>
    <div class="flex flex-wrap gap-2" aria-label="Filter areas">
        @foreach (['' => 'All areas', 'rising' => 'Prices rising', 'falling' => 'Prices falling', 'activity' => 'Activity changing'] as $group => $label)
            <a href="{{ route('insights.index', array_filter(['search' => $search, 'group' => $group, 'sort' => $sort])) }}" @if (($selectedGroup ?? '') === $group) aria-current="page" @endif class="rounded-full border px-4 py-2 text-sm font-medium {{ ($selectedGroup ?? '') === $group ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:border-lime-500' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-zinc-900">{{ $search !== '' ? 'Areas matching your search' : 'Explore local changes' }}</h2>
            <p class="mt-1 text-sm text-zinc-600">{{ number_format($query->total()) }} areas · One summary per area, using its latest available signal period.</p>
            @if ($search !== '' || ($selectedGroup ?? '') !== '' || $selectedType !== '')
                <a href="{{ route('insights.index') }}" class="mt-2 inline-block text-sm text-lime-700 underline">Clear search and filters</a>
            @endif
        </div>
        <form action="{{ route('insights.index') }}" method="GET" class="flex items-center gap-2">
            @foreach (['search' => $search, 'group' => $selectedGroup ?? '', 'type' => $selectedType] as $key => $value)
                @if ($value !== '') <input type="hidden" name="{{ $key }}" value="{{ $value }}"> @endif
            @endforeach
            <label for="insights-sort" class="text-sm text-zinc-600">Sort</label>
            <select id="insights-sort" name="sort" class="rounded-sm border-zinc-300 text-sm">
                @foreach (['sector_asc' => 'Postcode (A–Z)', 'sector_desc' => 'Postcode (Z–A)', 'transactions_desc' => 'Most recorded sales', 'transactions_asc' => 'Fewest recorded sales'] as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="rounded-sm border border-zinc-300 bg-white px-3 py-2 text-sm">Apply</button>
        </form>
    </div>
    <div class="grid gap-5 lg:grid-cols-2">
        @forelse ($query as $area)
            @php
                $signals = $area->relationLoaded('latestSignals') ? $area->latestSignals : collect([$area]);
                $labels = $signals->map(fn ($signal) => $signal->researchLabel())->unique();
                $evidence = $signals->pluck('supporting_data')->filter()->reduce(fn ($carry, $data) => array_replace($carry, $data), []);
            @endphp
            <article class="flex flex-col gap-4 rounded-sm border border-zinc-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="text-2xl font-semibold text-zinc-900">{{ $area->area_code }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($labels as $label) <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700">{{ $label }}</span> @endforeach
                    </div>
                </div>
                <div class="text-sm leading-6 text-zinc-700">
                    @foreach ($signals->unique(fn ($signal) => $signal->researchLabel()) as $signal)
                        <p>{{ $signal->researchFinding() }}</p>
                    @endforeach
                    @if (isset($evidence['price_growth'])) <p>Median price change: {{ number_format($evidence['price_growth'], 1) }}%.</p> @endif
                </div>
                <dl class="grid grid-cols-2 gap-4 border-y border-zinc-100 py-4 text-sm">
                    <div><dt class="text-zinc-500">Recorded sales</dt><dd class="mt-1 font-semibold text-zinc-900">{{ number_format($area->transactions) }}</dd></div>
                    <div><dt class="text-zinc-500">Previous period sales</dt><dd class="mt-1 font-semibold text-zinc-900">{{ isset($evidence['previous_sales']) ? number_format($evidence['previous_sales']) : 'Not available' }}</dd></div>
                    @foreach (['previous_median_price' => 'Previous median price', 'current_median_price' => 'Latest median price'] as $key => $label)
                        @if (isset($evidence[$key]))
                            <div><dt class="text-zinc-500">{{ $label }}</dt><dd class="mt-1 font-semibold text-zinc-900">£{{ number_format($evidence[$key]) }}</dd></div>
                        @endif
                    @endforeach
                    @if (isset($evidence['uk_growth']))
                        <div class="col-span-2"><dt class="text-zinc-500">National HPI benchmark change</dt><dd class="mt-1 text-zinc-800">{{ number_format($evidence['uk_growth'], 1) }}% · Uses a different methodology from local medians.</dd></div>
                    @endif
                    <div class="col-span-2"><dt class="text-zinc-500">Latest signal period</dt><dd class="mt-1 text-zinc-800">{{ optional($area->period_start)->format('d M Y') }} – {{ optional($area->period_end)->format('d M Y') }}</dd></div>
                </dl>
                <div class="text-sm leading-6 text-zinc-600">
                    <p class="font-semibold text-zinc-900">What to investigate next</p>
                    <p>{{ $labels->contains('Activity changing') ? 'Check whether recent registrations are complete and whether the sales slowdown persists in earlier periods.' : 'Compare property types and individual sales to see whether the mix of homes sold explains the price change.' }}</p>
                    <p class="mt-2 text-xs">Data completeness has not been assessed for this area.</p>
                </div>
                <a href="{{ route('insights.show', ['sector' => strtolower($area->area_code)]) }}" class="mt-auto text-sm font-semibold text-lime-700 underline decoration-lime-300 underline-offset-4 hover:text-lime-900">Explore prices and sales in {{ $area->area_code }} →</a>
            </article>
        @empty
            <div class="rounded-sm border border-dashed border-zinc-300 bg-white p-8 lg:col-span-2">
                <h3 class="font-semibold text-zinc-900">No matching areas with stored signals</h3>
                <p class="mt-2 text-sm text-zinc-600">Try a broader postcode or clear the filters. No signal does not mean an area is stable; it may have too few recorded sales or no change meeting the detection thresholds.</p>
                <a href="{{ route('property.search') }}" class="mt-4 inline-block text-sm font-semibold text-lime-700 underline">Research individual property sales</a>
            </div>
        @endforelse
    </div>
    @if ($query->hasPages()) <div>{{ $query->links() }}</div> @endif
    <details class="rounded-sm border border-zinc-200 bg-white p-5 text-sm leading-6 text-zinc-600">
        <summary class="cursor-pointer font-semibold text-zinc-900">How these areas are selected</summary>
        <p class="mt-3">Signals compare rolling 12-month periods. Related findings are combined into one area summary; overlapping older periods are omitted from this list. The latest stored signal may be older than the latest market data and does not establish that a change is continuing today.</p>
        <p class="mt-2">Sales counts describe recorded completions, not listings or buyer enquiries. Local median price changes are not adjusted for property mix and are not directly equivalent to the national House Price Index.</p>
    </details>
</div>
@endsection
