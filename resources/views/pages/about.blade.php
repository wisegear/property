@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 md:py-12">

    <section class="relative z-0 mb-12 overflow-hidden rounded-lg border border-zinc-200 bg-white p-8 shadow-sm">
        @include('partials.hero-background')
        <div class="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
                <h1 class="text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">
                    Independent UK Property Research
                </h1>
                <p class="mt-4 text-md leading-7 text-zinc-500">
                    PropertyResearch.uk helps people understand the UK property market through official data, clear analysis and practical tools.
                </p>
                <p class="mt-4 text-md leading-7 text-zinc-500">
                    The platform brings together housing, economic and local area datasets so users can explore property sales, market trends and neighbourhood context in one place.
                </p>
            </div>
            <div class="mx-auto w-full max-w-sm shrink-0 lg:mx-0">
                <img src="{{ asset('/assets/images/site/about.jpg') }}" alt="About Property Research" class="h-auto w-full">
            </div>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-xl font-semibold text-zinc-900">Why the platform exists</h2>
        <div class="mt-4 space-y-4 text-md leading-7 text-zinc-700">
            <p>
                Official property data is valuable, but it is often spread across different public sources and difficult to interpret without context.
            </p>
            <p>
                PropertyResearch.uk brings those datasets together and turns them into clear tools, dashboards and written analysis. The aim is not to predict the market, but to make it easier to understand.
            </p>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-xl font-semibold text-zinc-900">Why trust PropertyResearch.uk?</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <article class="rounded-lg border border-zinc-200 bg-white p-5">
                <h3 class="text-base font-semibold text-zinc-900">Official data</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Built from public datasets including HM Land Registry, the Bank of England, EPC registers, official court statistics, crime data and deprivation indices.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-white p-5">
                <h3 class="text-base font-semibold text-zinc-900">Independent</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    No sponsored rankings, paid placements or commercial influence over the analysis.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-white p-5">
                <h3 class="text-base font-semibold text-zinc-900">Transparent</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Sources and methods are explained wherever practical so the work can be understood and challenged.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-white p-5">
                <h3 class="text-base font-semibold text-zinc-900">Regularly updated</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Datasets are refreshed as new official releases become available.
                </p>
            </article>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-xl font-semibold text-zinc-900">What the platform covers</h2>
        <div class="mt-4 space-y-4 text-md leading-7 text-zinc-700">
            <p>
                PropertyResearch.uk covers property sales, street-level research, EPC data, crime and deprivation context, interest rates, mortgage approvals, housing market dashboards, Prime Central London and wider market indicators.
            </p>
            <p>
                The value is in connecting these datasets, so users can move from a single street or postcode to the wider market picture.
            </p>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-xl font-semibold text-zinc-900">How the data is handled</h2>
        <div class="mt-4 space-y-4 text-md leading-7 text-zinc-700">
            <p>
                The platform uses automated import pipelines, data cleaning, geographic matching and repeatable calculations to turn large public datasets into usable tools.
            </p>
            <p>
                Performance matters because many datasets are very large. The site is built to keep searches, dashboards and location pages fast enough to be useful.
            </p>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-xl font-semibold text-zinc-900">About the creator</h2>
        <div class="mt-4 space-y-4 text-md leading-7 text-zinc-700">
            <p>
                PropertyResearch.uk was created and is maintained by Lee Wisener, who has spent much of his career in financial services working with Mortgages since 2000 and has a long-standing interest in property data, software development and making complex information easier to understand.
            </p>
        </div>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <p class="max-w-3xl text-md leading-7 text-zinc-700">
            PropertyResearch.uk continues to evolve as new datasets become available and new ideas emerge. Feedback, corrections and suggestions are welcome.
        </p>
    </section>

</div>
@endsection
