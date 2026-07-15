<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div>
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-base font-semibold text-zinc-700">Nearby streets</h3>
            @if($snapshot['outcode'])
                <span class="text-xs text-zinc-500">{{ $snapshot['outcode'] }} area</span>
            @endif
        </div>
        @if($snapshot['nearby_streets'])
            <div class="mt-3 divide-y divide-zinc-100 rounded border border-zinc-200">
                @foreach($snapshot['nearby_streets'] as $street)
                    <a href="{{ $street['url'] }}" class="flex items-center justify-between gap-4 p-3 hover:bg-zinc-50">
                        <span>
                            <span class="block font-medium text-zinc-800">{{ $street['name'] }}</span>
                            <span class="block text-xs text-zinc-500">{{ number_format($street['sales_count']) }} sales in five years</span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold text-zinc-700">{{ $street['average_price_label'] }} avg.</span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="mt-3 rounded border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">No nearby street sales were found.</p>
        @endif
    </div>

    <div>
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-base font-semibold text-zinc-700">Latest sold prices</h3>
            @if($snapshot['updated_label'])
                <span class="text-xs text-zinc-500">Latest: {{ $snapshot['updated_label'] }}</span>
            @endif
        </div>
        @if($snapshot['recent_sales'])
            <div class="mt-3 divide-y divide-zinc-100 rounded border border-zinc-200">
                @foreach($snapshot['recent_sales'] as $sale)
                    <div class="flex items-start justify-between gap-4 p-3">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-zinc-800">{{ $sale['address'] }}</span>
                            <span class="block text-xs text-zinc-500">{{ $sale['postcode'] }} · {{ $sale['property_type'] }} · {{ $sale['date_label'] }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold text-zinc-700">{{ $sale['price_label'] }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-3 rounded border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">No recent sold prices were found.</p>
        @endif
    </div>
</div>

<p class="mt-4 text-xs leading-5 text-zinc-500">Land Registry sold-price records for the school’s postcode area. Figures are historical and do not represent a property valuation.</p>
