@extends('layouts.app')

@section('content')
    <section class="relative -mx-6 -mt-6 overflow-hidden bg-white px-4 py-12 sm:px-8 lg:py-16">
        <div class="relative mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.3fr_1fr]">
            <div class="max-w-3xl">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-800"><span class="h-2 w-2 rounded-full bg-emerald-600" aria-hidden="true"></span>About PropertyResearch.uk</p>
                <h1 class="mt-5 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">Independent UK Property Research</h1>
                <p class="mt-6 text-lg leading-8 text-slate-700">PropertyResearch.uk helps people understand the UK property market through official data, clear analysis and practical tools.</p>
                <p class="mt-4 text-sm leading-7 text-slate-600">The platform brings together housing, economic and local area datasets so users can explore property sales, market trends and neighbourhood context in one place.</p>
                <div class="mt-7 flex flex-wrap gap-2 text-xs font-medium text-emerald-900">
                    <span class="rounded-full border border-emerald-200 bg-white/80 px-3 py-1.5">Official data</span>
                    <span class="rounded-full border border-emerald-200 bg-white/80 px-3 py-1.5">Independent analysis</span>
                    <span class="rounded-full border border-emerald-200 bg-white/80 px-3 py-1.5">Practical tools</span>
                </div>
            </div>
            <div class="mx-auto w-full max-w-md">
                <img src="{{ asset('/assets/images/site/about.jpg') }}" alt="About Property Research" class="h-auto w-full">
            </div>
        </div>
    </section>
<div class="mx-auto grid max-w-7xl gap-6 px-0 py-8 md:grid-cols-2 lg:gap-8 lg:py-12">

    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 md:col-span-2 md:p-8">
        <h2 class="text-2xl font-semibold tracking-tight text-emerald-950">Why the platform exists</h2>
        <div class="mt-4 space-y-4 text-base leading-7 text-zinc-700">
            <p>
                Official property data is valuable, but it is often spread across different public sources and difficult to interpret without context.
            </p>
            <p>
                PropertyResearch.uk brings those datasets together and turns them into clear tools, dashboards and written analysis. The aim is not to predict the market, but to make it easier to understand.
            </p>
        </div>
    </section>

    <section class="md:col-span-2">
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Why trust PropertyResearch.uk?</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-sm font-semibold text-emerald-800" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h9l3 3v15H6zM9 10h6M9 14h6M9 18h4" /></svg></span>
                <h3 class="text-base font-semibold text-zinc-900">Official data</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Built from public datasets including HM Land Registry, the Bank of England, EPC registers, official court statistics, crime data and deprivation indices.
                </p>
            </article>
            <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-xl bg-sky-100 text-sm font-semibold text-sky-800" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7zM8 12l3 3 5-6" /></svg></span>
                <h3 class="text-base font-semibold text-zinc-900">Independent</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    No sponsored rankings, paid placements or commercial influence over the analysis.
                </p>
            </article>
            <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 text-sm font-semibold text-violet-800" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6" /></svg></span>
                <h3 class="text-base font-semibold text-zinc-900">Transparent</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Sources and methods are explained wherever practical so the work can be understood and challenged.
                </p>
            </article>
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-sm font-semibold text-amber-800" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7v5h-5M4 17v-5h5M6 7a7 7 0 0 1 12-1l2 3M18 17a7 7 0 0 1-12 1l-2-3" /></svg></span>
                <h3 class="text-base font-semibold text-zinc-900">Regularly updated</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Datasets are refreshed as new official releases become available.
                </p>
            </article>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-xl font-semibold text-zinc-900">What the platform covers</h2>
        <div class="mt-4 space-y-4 text-base leading-7 text-zinc-700">
            <p>
                PropertyResearch.uk covers property sales, street-level research, EPC data, crime and deprivation context, interest rates, mortgage approvals, housing market dashboards, Prime Central London and wider market indicators.
            </p>
            <p>
                The value is in connecting these datasets, so users can move from a single street or postcode to the wider market picture.
            </p>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
        <h2 class="text-xl font-semibold text-zinc-900">How the data is handled</h2>
        <div class="mt-4 space-y-4 text-base leading-7 text-zinc-700">
            <p>
                The platform uses automated import pipelines, data cleaning, geographic matching and repeatable calculations to turn large public datasets into usable tools.
            </p>
            <p>
                Performance matters because many datasets are very large. The site is built to keep searches, dashboards and location pages fast enough to be useful.
            </p>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 border-t-4 border-t-emerald-600 bg-white p-6 shadow-sm md:p-8">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-emerald-700">The person behind the platform</p>
        <h2 class="text-xl font-semibold text-zinc-900">About the creator</h2>
        <div class="mt-4 space-y-4 text-base leading-7 text-zinc-700">
            <p>
                PropertyResearch.uk was created and is maintained by Lee Wisener, who has spent much of his career in financial services working with Mortgages since 2000 and has a long-standing interest in property data, software development and making complex information easier to understand.
            </p>
        </div>
    </section>

    <section class="flex flex-col justify-center rounded-2xl bg-slate-900 p-6 md:p-8">
        <h2 class="mb-4 text-xl font-semibold text-emerald-300">Help shape what comes next</h2>
        <p class="max-w-3xl text-base leading-7 text-slate-300">
            PropertyResearch.uk continues to evolve as new datasets become available and new ideas emerge. Feedback, corrections and suggestions are welcome.
        </p>
    </section>

</div>
@endsection
