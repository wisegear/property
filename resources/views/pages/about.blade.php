@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 md:py-10">

    <section class="relative z-0 mb-10 overflow-hidden rounded-lg border border-zinc-200 bg-white p-8 shadow-sm">
        @include('partials.hero-background')
        <div class="relative z-10 flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-4xl">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-lime-700">About PropertyResearch.uk</p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 md:text-5xl">
                    Independent UK Property Research
                </h1>
                <p class="mt-4 max-w-3xl text-base leading-8 text-zinc-600 md:text-lg">
                    PropertyResearch.uk brings together official housing, economic and demographic datasets to provide clear, independent insight into the UK property market. Every dashboard, article and analysis is built using trusted public data sources and presented objectively, without sensationalism or commercial influence.
                </p>
            </div>
            <div class="mx-auto w-full max-w-sm shrink-0 lg:mx-0">
                <img src="{{ asset('/assets/images/site/about.jpg') }}" alt="About Property Research" class="h-auto w-full">
            </div>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <h2 class="text-2xl font-semibold tracking-tight text-zinc-900">Our Mission</h2>
                <p class="mt-3 text-sm leading-7 text-zinc-700 md:text-base">
                    To make UK property data more accessible, transparent and understandable by transforming official datasets into practical tools, dashboards and insights that anyone can use.
                </p>
            </div>
            <div class="grid gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-5 text-sm text-zinc-700 sm:grid-cols-2 lg:max-w-xl">
                <div class="rounded-md border border-zinc-200 bg-white p-4">
                    <p class="font-semibold text-zinc-900">Coverage</p>
                    <p class="mt-2 leading-6">Housing, mortgage, rental, deprivation, repossession and demographic data in one place.</p>
                </div>
                <div class="rounded-md border border-zinc-200 bg-white p-4">
                    <p class="font-semibold text-zinc-900">Purpose</p>
                    <p class="mt-2 leading-6">Clear analysis for buyers, sellers, journalists, researchers and property professionals.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <div class="max-w-3xl">
            <h2 class="text-2xl font-semibold tracking-tight text-zinc-900">Why Trust PropertyResearch.uk?</h2>
            <p class="mt-3 text-sm leading-7 text-zinc-700 md:text-base">
                The platform is designed to make official UK property data easier to understand without introducing commercial bias or opaque methodology.
            </p>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                <h3 class="text-lg font-semibold text-zinc-900">Official Data</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Built using official datasets including HM Land Registry, the Bank of England, EPC registers, Ministry of Justice statistics, Police UK, deprivation indices and other public sources.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                <h3 class="text-lg font-semibold text-zinc-900">Independent Research</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    No sponsored rankings, no paid placements and no commercial influence over the analysis or conclusions presented on the site.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                <h3 class="text-lg font-semibold text-zinc-900">Transparent</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Sources are referenced wherever possible and methodologies are explained throughout the platform so readers can understand how the numbers are produced.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                <h3 class="text-lg font-semibold text-zinc-900">Regularly Updated</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Data is refreshed automatically as new official releases become available, helping the platform stay current as the market changes.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                <h3 class="text-lg font-semibold text-zinc-900">Built for Everyone</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Designed for buyers, sellers, researchers, journalists, mortgage professionals, investors and anyone interested in the UK property market.
                </p>
            </article>
            <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                <h3 class="text-lg font-semibold text-zinc-900">Independent by Design</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-700">
                    Because the platform is independently funded, there is no need for subscriptions, sponsored content or advertising-driven articles. That supports objectivity rather than monetisation incentives.
                </p>
            </article>
        </div>
    </section>

    <section class="mb-8 grid gap-8 lg:grid-cols-[1.05fr_0.95fr]">
        <article class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
            <h2 class="text-2xl font-semibold tracking-tight text-zinc-900">Data Sources</h2>
            <p class="mt-3 text-sm leading-7 text-zinc-700 md:text-base">
                PropertyResearch.uk is built around official public-sector datasets. Core sources include HM Land Registry, Registers of Scotland, the ONS Postcode Directory, deprivation indices across the UK, EPC records, Ministry of Justice court statistics, the Bank of England, Police UK and wider ONS and government releases covering housing, population and affordability.
            </p>
            <p class="mt-3 text-sm leading-7 text-zinc-700 md:text-base">
                Northern Ireland data appears in deprivation and map views, but postcode-level search remains limited by licensing restrictions, so a full postcode lookup cannot be published.
            </p>
        </article>
        <article class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
            <h2 class="text-2xl font-semibold tracking-tight text-zinc-900">Methodology</h2>
            <ul class="mt-4 grid gap-3 text-sm leading-6 text-zinc-700">
                <li class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3">Official public-sector datasets collected and standardised for consistent analysis.</li>
                <li class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3">Automated import pipelines designed to handle new releases efficiently.</li>
                <li class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3">Data cleaning and validation to reduce duplication, mismatch and noisy joins.</li>
                <li class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3">Repeatable calculations so charts and dashboards remain consistent and auditable.</li>
                <li class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3">Performance optimisation for very large datasets through targeted caching and precomputation.</li>
                <li class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3">Continuous improvements as new official data becomes available and the platform expands.</li>
            </ul>
        </article>
    </section>

    <section class="mb-8 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm md:p-8">
        <div class="grid gap-8 lg:grid-cols-[1fr_0.9fr]">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-zinc-900">What You&rsquo;ll Find</h2>
                <p class="mt-3 text-sm leading-7 text-zinc-700 md:text-base">
                    The site combines tools, dashboards and written analysis to help users explore how the UK property market behaves over time and across places. That includes sales trends, mortgage approvals, interest rates, rental data, repossession activity, deprivation measures, EPC records and area-level context drawn from official releases.
                </p>
                <p class="mt-3 text-sm leading-7 text-zinc-700 md:text-base">
                    The emphasis is on clear presentation, practical interpretation and data that can stand up to scrutiny.
                </p>
            </div>
            <div class="rounded-lg border border-lime-100 bg-lime-50/70 p-6">
                <h3 class="text-lg font-semibold text-zinc-900">How the platform is built</h3>
                <p class="mt-3 text-sm leading-7 text-zinc-700">
                    The strongest part of PropertyResearch.uk is the underlying data work: collecting official releases, cleaning them carefully, linking them with the right geographic identifiers and presenting them in a way that remains fast even at large scale.
                </p>
                <p class="mt-3 text-sm leading-7 text-zinc-700">
                    That engineering work is what turns raw public datasets into tools that are useful in practice rather than difficult to interpret.
                </p>
            </div>
        </div>
    </section>

    <section class="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">About Lee</h2>
        <p class="mt-3 text-sm leading-7 text-gray-800 md:text-base">
            I have spent much of my career in financial services working within mortgage lending and have a longstanding interest in data analysis, software engineering and building tools that make complex information easier to understand.
        </p>
        <p class="mt-3 text-sm leading-7 text-gray-800 md:text-base">
            PropertyResearch.uk brings those disciplines together in a focused way: combining official data, repeatable analysis and practical presentation to make UK property information more accessible.
        </p>
        <p class="mt-3 text-sm leading-7 text-gray-800 md:text-base">
            I work in financial services, but PropertyResearch.uk is created and maintained independently of my employer, and all commentary and analysis published here are my own.
        </p>
    </section>

    <section class="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">Privacy and analytics</h2>
        <p class="mt-3 text-sm leading-6 text-gray-800">
            This site may collect IP addresses for analytics, abuse prevention, spam prevention and general site security. Those logs are used internally for admin and security work, including bot filtering, suspicious traffic review and keeping the service usable.
        </p>
        <p class="mt-3 text-sm leading-6 text-gray-800">
            Any sponsor or partner reporting uses aggregated statistics only. That reporting excludes raw IP addresses, excludes individual visitor journeys and is designed to show clean, bot-filtered summary activity rather than person-level browsing history.
        </p>
    </section>

    <section class="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">Important context</h2>
        <ul class="mt-3 list-inside list-disc text-sm leading-6 text-gray-800">
            <li>Names aren’t keys. Where things must join, I use codes or a small alias map.</li>
            <li>Data lags exist. Official releases aren’t real‑time, and YTD isn’t a full year.</li>
            <li class="text-rose-700">This is analysis, not advice. Please don’t base a life decision on a single chart.</li>
        </ul>
    </section>

    <section class="mb-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">Licence Notes</h2>
        <p class="mt-3 text-sm leading-6 text-gray-800">
            Unless explicitly stated below, all data used on this site is published under the Open Government Licence v3.0.
        </p>
        <p class="mt-3 text-sm leading-6 text-gray-800">Contains HM Land Registry data © Crown copyright and database right 2020. Licensed under the Open Government Licence v3.0.</p>
        <p class="mt-3 text-sm leading-6 text-gray-800">
            Energy Performance Certificate (EPC) data for England and Wales is sourced from the official Energy Performance of Buildings Register. EPC information is displayed on a property‑by‑property, user‑requested basis and reflects the same records that are publicly available via the official register. This site does not publish or redistribute bulk address‑level EPC datasets. EPC records may be removed or unavailable where they are no longer publicly disclosed on the register.
        </p>
        <p class="mt-3 text-sm leading-6 text-gray-800">
            Scottish EPC data is sourced from the Scottish Government via statistics.gov.scot. Non‑address data fields within the Scottish Domestic Energy Performance Certificates dataset (all fields other than address and postcode information) are licensed under the Open Government Licence v3.0. Scottish EPC information on this site is displayed on a property‑by‑property, user‑requested basis and reflects records available via the official Scottish EPC Register.
        </p>
    </section>


    <section class="mb-10 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">Contact</h2>
        <p class="mt-3 text-sm leading-6 text-gray-800">
            Feedback, corrections and suggestions are always welcome. Improving the platform is just as important as building it. If you would like to get in touch, you can email me on lee@wisener.net or visit the <a href="/blog"><span class="text-lime-600 hover:text-lime-500 hover:underline">blog</span></a> for longer-form commentary.
        </p>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">Continuing to improve</h2>
        <p class="mt-3 text-sm leading-7 text-gray-800 md:text-base">
            PropertyResearch.uk continues to evolve as new official datasets become available and new ideas emerge. Careful feedback, corrections and informed suggestions all help make the platform more accurate, more useful and more transparent over time.
        </p>
    </section>

</div>
@endsection
