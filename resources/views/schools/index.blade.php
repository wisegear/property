@extends('layouts.app')

@section('title', 'Schools in England | PropertyResearch.uk')
@section('description', 'Search and explore schools in England using Ofsted inspection data, school details and local property information.')

@section('content')
    @php
        $ratingStyles = [
            '1' => ['dot' => 'bg-emerald-600', 'bar' => 'bg-emerald-600', 'border' => 'border-t-emerald-600'],
            '2' => ['dot' => 'bg-blue-600', 'bar' => 'bg-blue-600', 'border' => 'border-t-blue-600'],
            '3' => ['dot' => 'bg-amber-500', 'bar' => 'bg-amber-500', 'border' => 'border-t-amber-500'],
            '4' => ['dot' => 'bg-red-600', 'bar' => 'bg-red-600', 'border' => 'border-t-red-600'],
            'not_judged' => ['dot' => 'bg-violet-500', 'bar' => 'bg-violet-500', 'border' => 'border-t-violet-500'],
            'no_grade' => ['dot' => 'bg-zinc-400', 'bar' => 'bg-zinc-400', 'border' => 'border-t-zinc-400'],
        ];
    @endphp

    <section class="relative z-0 -mx-6 -mt-6 overflow-hidden border-b border-zinc-200 bg-white py-8 shadow-[0_1px_0_rgba(0,0,0,0.06)] md:py-9">
        <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[minmax(0,1fr)_minmax(280px,0.42fr)] md:gap-8">
            <div class="max-w-4xl">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500"><span class="size-2 rounded-full bg-lime-500"></span>England school research</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">Schools in England</h1>
                <p class="mt-3 max-w-3xl text-base leading-7 text-zinc-600">Search and explore schools in England using Ofsted inspection data, school details and local property information.</p>
                <p class="mt-4 max-w-3xl text-xs leading-5 text-zinc-500">
                    <span class="font-semibold text-zinc-700">Not judged</span> means Ofsted explicitly records no current graded overall judgement.
                    <span class="font-semibold text-zinc-700">No current overall grade</span> means the school matches an Ofsted record but its overall-effectiveness field is blank, which can include schools awaiting a graded inspection under their current URN or reports produced without an overall grade.
                </p>
            </div>
            <div class="hidden justify-self-end md:block">
                <img src="{{ asset('/assets/images/site/schools.jpg') }}" alt="Schools and local research" class="h-44 w-full max-w-sm object-cover [mask-image:linear-gradient(to_right,transparent,black_22%)]">
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-7 px-4 py-8">
        <section aria-labelledby="national-overview-title">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">National overview</p>
                    <h2 id="national-overview-title" class="mt-1 text-xl font-bold text-zinc-900">Open schools in the Ofsted dataset</h2>
                </div>
                <p class="hidden text-xs text-zinc-500 sm:block">Figures reflect the latest data held by PropertyResearch.uk</p>
            </div>

            <div class="mt-4 grid grid-cols-2 border-l border-t border-zinc-200 bg-white sm:grid-cols-4 lg:grid-cols-7">
                <div class="border-b border-r border-t-4 border-t-slate-800 border-zinc-200 p-4">
                    <p class="text-xs font-medium text-zinc-500">Total schools</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-950">{{ number_format($dashboard['total']) }}</p>
                </div>
                @foreach($dashboard['ratings'] as $rating)
                    <a href="#find-school" data-rating-link="{{ $rating['value'] }}" class="border-b border-r border-t-4 {{ $ratingStyles[$rating['value']]['border'] }} border-zinc-200 p-4 transition hover:bg-zinc-50">
                        <p class="text-xs font-medium text-zinc-500">{{ $rating['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-950">{{ number_format($rating['count']) }}</p>
                    </a>
                @endforeach
            </div>
            <p class="mt-2 text-xs leading-5 text-zinc-500">{{ number_format($dashboard['excluded']) }} other open establishments are excluded from these rating figures because they do not match the current Ofsted dataset. This includes establishments outside Ofsted’s remit and records without a matching URN.</p>
        </section>

        <div class="grid gap-7 lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
            <section class="border border-zinc-200 bg-white p-5 shadow-sm" aria-labelledby="rating-distribution-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="rating-distribution-title" class="text-lg font-bold text-zinc-900">Ofsted rating distribution</h2>
                        <p class="mt-1 text-sm text-zinc-500">Current overall effectiveness data for matched open schools.</p>
                    </div>
                    <span class="text-xs font-medium text-zinc-400">{{ number_format($dashboard['total']) }} schools</span>
                </div>

                <div class="mt-5 flex h-3 w-full overflow-hidden bg-zinc-100" aria-hidden="true">
                    @foreach($dashboard['ratings'] as $rating)
                        <div class="{{ $ratingStyles[$rating['value']]['bar'] }}" style="width: {{ $rating['percentage'] }}%"></div>
                    @endforeach
                </div>

                <div class="mt-5 grid gap-3">
                    @foreach($dashboard['ratings'] as $rating)
                        <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 text-sm">
                            <div class="flex min-w-0 items-center gap-2"><span class="size-2 shrink-0 {{ $ratingStyles[$rating['value']]['dot'] }}"></span><span class="truncate text-zinc-700">{{ $rating['label'] }}</span></div>
                            <span class="tabular-nums text-zinc-500">{{ number_format($rating['count']) }}</span>
                            <span class="w-12 text-right font-semibold tabular-nums text-zinc-900">{{ number_format($rating['percentage'], 1) }}%</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="border border-zinc-200 bg-white p-5 shadow-sm" aria-labelledby="school-landscape-title">
                <h2 id="school-landscape-title" class="text-lg font-bold text-zinc-900">School landscape</h2>
                <p class="mt-1 text-sm text-zinc-500">Largest phases recorded in Department for Education data.</p>
                <dl class="mt-4 divide-y divide-zinc-100 border-t border-zinc-100">
                    @forelse($dashboard['landscape'] as $item)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="text-sm text-zinc-600">{{ $item['label'] }}</dt>
                            <dd class="text-sm font-bold tabular-nums text-zinc-900">{{ number_format($item['count']) }}</dd>
                        </div>
                    @empty
                        <div class="py-4 text-sm text-zinc-500">Phase information is not currently available.</div>
                    @endforelse
                </dl>
            </section>
        </div>

        <section id="find-school" class="scroll-mt-4 border border-zinc-300 bg-white shadow-sm" x-data="schoolFinder(@js($results), @js($pagination), @js(route('schools.search')), @js($search))">
            <div class="border-b border-zinc-200 bg-zinc-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Search and compare</p>
                <h2 class="mt-1 text-xl font-bold text-zinc-900">Find a school</h2>
                <p class="mt-1 text-sm text-zinc-600">Use any combination of search and filters. All matching schools are available in paginated results.</p>
            </div>

            <form action="{{ route('schools.index') }}" method="GET" class="grid gap-4 border-b border-zinc-200 p-5 lg:grid-cols-[minmax(250px,1.5fr)_repeat(3,minmax(150px,1fr))_auto]" @submit.prevent="runSearch()">
                <div>
                    <label for="school-search" class="text-xs font-semibold text-zinc-700">School name or postcode</label>
                    <input id="school-search" name="q" x-model="form.q" @input.debounce.300ms="runSearch()" placeholder="e.g. Kings Academy or SW7" class="mt-1.5 w-full border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 outline-none focus:border-lime-500 focus:ring-1 focus:ring-lime-500">
                </div>
                <div>
                    <label for="school-rating" class="text-xs font-semibold text-zinc-700">Ofsted rating</label>
                    <select id="school-rating" name="rating" x-model="form.rating" @change="runSearch()" class="mt-1.5 w-full border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-lime-500 focus:ring-lime-500">
                        <option value="">All ratings</option>
                        @foreach($dashboard['ratings'] as $rating)<option value="{{ $rating['value'] }}">{{ $rating['label'] }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="school-phase" class="text-xs font-semibold text-zinc-700">School phase</label>
                    <select id="school-phase" name="phase" x-model="form.phase" @change="runSearch()" class="mt-1.5 w-full border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-lime-500 focus:ring-lime-500">
                        <option value="">All phases</option>
                        @foreach($filters['phases'] as $phase)<option value="{{ $phase }}">{{ Str::headline($phase) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="school-authority" class="text-xs font-semibold text-zinc-700">Local authority</label>
                    <select id="school-authority" name="local_authority" x-model="form.local_authority" @change="runSearch()" class="mt-1.5 w-full border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-lime-500 focus:ring-lime-500">
                        <option value="">All authorities</option>
                        @foreach($filters['localAuthorities'] as $authority)<option value="{{ $authority }}">{{ $authority }}</option>@endforeach
                    </select>
                </div>
                <button class="self-end bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Search</button>
            </form>

            <div class="min-h-36" aria-live="polite" aria-busy="false" :aria-busy="loading.toString()">
                <div x-show="loading" class="flex items-center gap-3 p-5 text-sm text-zinc-500"><span class="size-4 animate-spin rounded-full border-2 border-zinc-300 border-t-slate-800"></span>Searching schools…</div>
                <div x-show="error && !loading" x-cloak class="p-5 text-sm text-red-700" x-text="error"></div>
                <div x-show="searched && !loading && !error && results.length === 0" x-cloak class="p-8 text-center">
                    <p class="font-semibold text-zinc-800">No matching schools found</p>
                    <p class="mt-1 text-sm text-zinc-500">Try a shorter school name, postcode area or fewer filters.</p>
                </div>
                <div x-show="!searched && !loading" class="p-8 text-center">
                    <p class="font-semibold text-zinc-800">Start with a school name or postcode</p>
                    <p class="mt-1 text-sm text-zinc-500">Results appear here as you type.</p>
                </div>
                <div x-show="results.length > 0 && !loading" x-cloak class="divide-y divide-zinc-100">
                    <template x-for="school in results" :key="school.url">
                        <article class="grid gap-3 px-5 py-4 transition hover:bg-zinc-50 md:grid-cols-[minmax(0,1.5fr)_minmax(180px,0.8fr)_auto] md:items-center">
                            <div class="min-w-0">
                                <a :href="school.url" class="font-semibold text-zinc-950 hover:text-lime-700 hover:underline" x-text="school.name"></a>
                                <p class="mt-1 text-xs text-zinc-500"><span x-text="school.town || school.local_authority || 'England'"></span><span x-show="school.postcode"> · <span x-text="school.postcode"></span></span></p>
                            </div>
                            <p class="text-sm text-zinc-600" x-text="school.phase || school.type || 'School' "></p>
                            <span class="w-fit border px-2 py-1 text-xs font-semibold" :class="school.rating_class" x-text="school.rating"></span>
                        </article>
                    </template>
                </div>
                <div x-show="pagination && pagination.total > 0 && !loading" x-cloak class="flex flex-col gap-3 border-t border-zinc-200 bg-zinc-50 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-zinc-500">Showing <span class="font-medium text-zinc-700" x-text="pagination ? pagination.from : ''"></span>–<span class="font-medium text-zinc-700" x-text="pagination ? pagination.to : ''"></span> of <span class="font-medium text-zinc-700" x-text="pagination ? pagination.total.toLocaleString('en-GB') : ''"></span> schools</p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="goToPage(pagination.current_page - 1)" :disabled="!pagination || pagination.current_page <= 1" class="border border-zinc-300 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
                        <span class="px-2 text-xs text-zinc-500">Page <span x-text="pagination ? pagination.current_page : ''"></span> of <span x-text="pagination ? pagination.last_page : ''"></span></span>
                        <button type="button" @click="goToPage(pagination.current_page + 1)" :disabled="!pagination || pagination.current_page >= pagination.last_page" class="border border-zinc-300 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-40">Next</button>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="explore-schools-title">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-zinc-500">Browse the data</p>
                <h2 id="explore-schools-title" class="mt-1 text-xl font-bold text-zinc-900">Explore schools</h2>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="border border-zinc-200 bg-white p-5">
                    <h3 class="text-sm font-bold text-zinc-900">Browse by Ofsted rating</h3>
                    <div class="mt-3 grid grid-cols-2 gap-px border border-zinc-200 bg-zinc-200">
                        @foreach($dashboard['ratings'] as $rating)
                            <a href="{{ route('schools.index', ['rating' => $rating['value']]) }}#find-school" class="bg-white p-3 text-sm text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950">{{ $rating['label'] }} <span class="block text-xs text-zinc-400">{{ number_format($rating['count']) }} schools</span></a>
                        @endforeach
                    </div>
                </div>
                <div class="border border-zinc-200 bg-white p-5">
                    <h3 class="text-sm font-bold text-zinc-900">Browse by school phase</h3>
                    <div class="mt-3 grid grid-cols-2 gap-px border border-zinc-200 bg-zinc-200">
                        @foreach(array_slice($dashboard['landscape'], 0, 6) as $item)
                            <a href="{{ route('schools.index', ['phase' => $item['value']]) }}#find-school" class="bg-white p-3 text-sm text-zinc-700 hover:bg-zinc-50 hover:text-zinc-950">{{ $item['label'] }} <span class="block text-xs text-zinc-400">{{ number_format($item['count']) }} schools</span></a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        function schoolFinder(initialResults, initialPagination, endpoint, initialSearch) {
            return {
                results: initialResults,
                pagination: initialPagination,
                endpoint,
                loading: false,
                error: '',
                searched: initialResults.length > 0 || Object.values(initialSearch).some(Boolean),
                requestNumber: 0,
                form: {
                    q: initialSearch.q || '',
                    rating: initialSearch.rating || '',
                    phase: initialSearch.phase || '',
                    local_authority: initialSearch.local_authority || '',
                },
                init() {
                    document.querySelectorAll('[data-rating-link]').forEach((link) => {
                        link.addEventListener('click', () => {
                            this.form.rating = link.dataset.ratingLink;
                            this.runSearch();
                        });
                    });
                },
                goToPage(page) {
                    if (page < 1 || (this.pagination && page > this.pagination.last_page)) return;
                    this.runSearch(page);
                },
                async runSearch(page = 1) {
                    if (!Object.values(this.form).some((value) => value.trim() !== '')) {
                        this.results = [];
                        this.pagination = null;
                        this.searched = false;
                        return;
                    }

                    const currentRequest = ++this.requestNumber;
                    const parameters = new URLSearchParams(Object.entries(this.form).filter(([, value]) => value.trim() !== ''));
                    parameters.set('page', page);
                    this.loading = true;
                    this.error = '';

                    try {
                        const response = await fetch(`${this.endpoint}?${parameters}`, { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error('Search is temporarily unavailable. Please try again.');
                        const payload = await response.json();
                        if (currentRequest === this.requestNumber) {
                            this.results = payload.data;
                            this.pagination = payload.pagination;
                            this.searched = true;
                        }
                    } catch (error) {
                        if (currentRequest === this.requestNumber) this.error = error.message;
                    } finally {
                        if (currentRequest === this.requestNumber) this.loading = false;
                    }
                },
            };
        }
    </script>
@endpush
