@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10">
    <section class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
        @include('partials.hero-background')
        <div class="relative z-10 max-w-3xl">
            <div class="inline-flex items-center rounded-md bg-lime-50 px-3 py-1 text-xs font-medium text-lime-700 ring-1 ring-inset ring-lime-600/20">
                Sponsor-ready analytics
            </div>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">Audience Summary</h1>
            <p class="mt-3 text-sm leading-7 text-zinc-600">
                This dashboard uses aggregated, bot-filtered analytics only. It excludes raw IP addresses, individual visitor journeys, and admin/security-only signals.
            </p>
        </div>
    </section>

    <section class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">Unique visitors</p>
            <p class="mt-3 text-3xl font-semibold text-zinc-900">{{ number_format($stats['windows'][30]['unique_visitors']) }}</p>
            <p class="mt-1 text-sm text-zinc-500">Last 30 days</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">Unique visitors</p>
            <p class="mt-3 text-3xl font-semibold text-zinc-900">{{ number_format($stats['windows'][90]['unique_visitors']) }}</p>
            <p class="mt-1 text-sm text-zinc-500">Last 90 days</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">Page views</p>
            <p class="mt-3 text-3xl font-semibold text-zinc-900">{{ number_format($stats['windows'][30]['page_views']) }}</p>
            <p class="mt-1 text-sm text-zinc-500">Last 30 days</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">UK visitor share</p>
            <p class="mt-3 text-3xl font-semibold text-zinc-900">{{ number_format($stats['uk_visitor_percentage'], 1) }}%</p>
            <p class="mt-1 text-sm text-zinc-500">Last 30 days</p>
        </div>
    </section>

    <section class="mt-8 grid gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Top Content Categories</h2>
            <div class="mt-4 space-y-2 text-sm text-zinc-700">
                @forelse($stats['top_content_categories'] as $row)
                    <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                        <span>{{ $row->page_type }}</span>
                        <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                    </div>
                @empty
                    <p class="text-zinc-500">No category data yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Top Countries</h2>
            <div class="mt-4 space-y-2 text-sm text-zinc-700">
                @forelse($stats['top_countries'] as $row)
                    <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                        <span>{{ $row->country_code }}</span>
                        <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                    </div>
                @empty
                    <p class="text-zinc-500">No country data yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Top Landing Pages</h2>
            <div class="mt-4 space-y-2 text-sm text-zinc-700">
                @forelse($stats['top_landing_pages'] as $row)
                    <div class="flex items-center justify-between gap-4 rounded-md bg-zinc-50 px-3 py-2">
                        <span class="truncate">{{ $row->landing_page }}</span>
                        <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                    </div>
                @empty
                    <p class="text-zinc-500">No landing page data yet.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-zinc-900">Search And Tool Usage</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-zinc-200 p-4">
                <p class="text-sm text-zinc-500">Postcode and property searches</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($stats['event_totals']['postcode_property_searches']) }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4">
                <p class="text-sm text-zinc-500">Street searches</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($stats['event_totals']['street_searches']) }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4">
                <p class="text-sm text-zinc-500">EPC lookups</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($stats['event_totals']['epc_lookups']) }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4">
                <p class="text-sm text-zinc-500">Deprivation lookups</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($stats['event_totals']['deprivation_lookups']) }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4">
                <p class="text-sm text-zinc-500">Mortgage calculator uses</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($stats['event_totals']['mortgage_calculator_uses']) }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4">
                <p class="text-sm text-zinc-500">Stamp duty calculator uses</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($stats['event_totals']['stamp_duty_calculator_uses']) }}</p>
            </div>
        </div>
    </section>

    <section class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-zinc-900">Dataset Scale</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($stats['dataset_scale_cards'] as $card)
                <div class="rounded-lg border border-zinc-200 p-4">
                    <p class="text-sm text-zinc-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format($card['value']) }}</p>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
