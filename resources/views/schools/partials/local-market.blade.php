<div class="grid grid-cols-1 gap-6 {{ $snapshot['nearby_streets'] ? 'lg:grid-cols-2' : '' }}">
    @if($snapshot['nearby_streets'])
        <div>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-zinc-700">Nearby streets</h3>
                    <p class="mt-0.5 text-xs text-zinc-500">Average sold price over the past 12 months.</p>
                </div>
                @if($snapshot['outcode'])
                    <span class="text-xs text-zinc-500">{{ $snapshot['outcode'] }} area</span>
                @endif
            </div>
            <div class="mt-3 divide-y divide-zinc-100 rounded border border-zinc-200">
                @foreach($snapshot['nearby_streets'] as $street)
                    <a href="{{ $street['url'] }}" class="group flex items-center justify-between gap-4 p-3 transition-colors hover:bg-lime-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-600">
                        <span>
                            <span class="block font-medium text-zinc-800 underline decoration-zinc-300 underline-offset-2 group-hover:text-lime-800 group-hover:decoration-lime-600">{{ $street['name'] }}</span>
                            <span class="block text-xs text-zinc-500">{{ number_format($street['sales_count']) }} sales in 12 months</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-3">
                            <span class="text-right">
                                <span class="block text-sm font-semibold text-zinc-700">{{ $street['average_price_label'] }}</span>
                                <span class="block text-xs font-semibold text-lime-700">View street</span>
                            </span>
                            <svg class="size-4 text-lime-700 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 0 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-zinc-700">Latest sold prices</h3>
                <p class="mt-0.5 text-xs text-zinc-500">The most recent sales recorded on nearby streets.</p>
            </div>
            @if($snapshot['updated_label'])
                <span class="text-xs text-zinc-500">Latest: {{ $snapshot['updated_label'] }}</span>
            @endif
        </div>
        @if($snapshot['recent_sales'])
            <div class="mt-3 divide-y divide-zinc-100 rounded border border-zinc-200">
                @foreach($snapshot['recent_sales'] as $sale)
                    <a href="{{ $sale['url'] }}" class="group flex items-start justify-between gap-4 p-3 transition-colors hover:bg-lime-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lime-600">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-zinc-800 underline decoration-zinc-300 underline-offset-2 group-hover:text-lime-800 group-hover:decoration-lime-600">{{ $sale['address'] }}</span>
                            <span class="block text-xs text-zinc-500">{{ $sale['postcode'] }} · {{ $sale['property_type'] }} · {{ $sale['date_label'] }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-3">
                            <span class="text-right">
                                <span class="block text-sm font-semibold text-zinc-700">{{ $sale['price_label'] }}</span>
                                <span class="block text-xs font-semibold text-lime-700">View property</span>
                            </span>
                            <svg class="size-4 text-lime-700 transition-transform group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 0 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="mt-3 rounded border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">No recent sold prices were found.</p>
        @endif
    </div>
</div>

<p class="mt-4 text-xs leading-5 text-zinc-500">Land Registry sold-price records for the school’s postcode area. Figures are historical and do not represent a property valuation.</p>
