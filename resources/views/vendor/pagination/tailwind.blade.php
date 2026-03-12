@if ($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $pages = collect([1]);

        if ($lastPage > 1) {
            $pages = $pages
                ->push($currentPage - 1)
                ->push($currentPage)
                ->push($currentPage + 1)
                ->push($lastPage);
        }

        $pages = $pages
            ->filter(fn (int $page): bool => $page >= 1 && $page <= $lastPage)
            ->unique()
            ->sort()
            ->values();
    @endphp

    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-500">
                    Previous
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-700 transition hover:text-gray-500 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 active:bg-gray-100 active:text-gray-700">
                    Previous
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-700 transition hover:text-gray-500 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 active:bg-gray-100 active:text-gray-700">
                    Next
                </a>
            @else
                <span class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium leading-5 text-gray-500">
                    Next
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm leading-5 text-gray-700">
                    Showing
                    @if ($paginator->firstItem())
                        <span class="font-medium">{{ $paginator->firstItem() }}</span>
                        to
                        <span class="font-medium">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    of
                    <span class="font-medium">{{ $paginator->total() }}</span>
                    insights
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex items-center gap-2">
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" class="inline-flex items-center rounded-md px-3 py-1.5 text-sm text-gray-400">
                            Previous
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-200" aria-label="{{ __('pagination.previous') }}">
                            Previous
                        </a>
                    @endif

                    @foreach ($pages as $index => $page)
                        @php
                            $previousPage = $pages->get($index - 1);
                        @endphp

                        @if ($previousPage !== null && $page - $previousPage > 1)
                            <span aria-hidden="true" class="px-1.5 text-sm text-gray-500">…</span>
                        @endif

                        @if ($page === $currentPage)
                            <span aria-current="page" class="inline-flex items-center rounded-md border border-gray-300 bg-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-900">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $paginator->url($page) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-200" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-200" aria-label="{{ __('pagination.next') }}">
                            Next
                        </a>
                    @else
                        <span aria-disabled="true" class="inline-flex items-center rounded-md px-3 py-1.5 text-sm text-gray-400">
                            Next
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
