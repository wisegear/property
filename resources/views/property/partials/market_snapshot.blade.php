<div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Last 12 Months Sales</div>
        <div id="snapshot-sales" class="mt-3 text-2xl font-semibold text-zinc-900">
            {{ number_format($snapshot['rolling_12_sales'] ?? 0) }}
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Median Price</div>
        <div id="snapshot-median-price" class="mt-3 text-2xl font-semibold text-zinc-900">
            £{{ number_format($snapshot['rolling_12_median_price'] ?? 0) }}
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Median Price Change</div>
        <div
            id="snapshot-price-yoy"
            class="mt-3 text-2xl font-semibold {{ ($snapshot['rolling_12_price_yoy'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}"
        >
            {{ number_format($snapshot['rolling_12_price_yoy'] ?? 0, 1) }}%
        </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-center shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Sale Volume Change</div>
        <div
            id="snapshot-sales-yoy"
            class="mt-3 text-2xl font-semibold {{ ($snapshot['rolling_12_sales_yoy'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}"
        >
            {{ number_format($snapshot['rolling_12_sales_yoy'] ?? 0, 1) }}%
        </div>
    </div>
</div>
