<div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-4">
    <div class="rounded bg-white p-4 text-center shadow">
        <div class="text-xs uppercase text-gray-500">LAST 12 MONTHS SALES</div>
        <div id="snapshot-sales" class="text-2xl font-semibold">
            {{ number_format($snapshot['rolling_12_sales'] ?? 0) }}
        </div>
    </div>

    <div class="rounded bg-white p-4 text-center shadow">
        <div class="text-xs uppercase text-gray-500">Median Price</div>
        <div id="snapshot-median-price" class="text-2xl font-semibold">
            £{{ number_format($snapshot['rolling_12_median_price'] ?? 0) }}
        </div>
    </div>

    <div class="rounded bg-white p-4 text-center shadow">
        <div class="text-xs uppercase text-gray-500">MEDIAN PRICE CHANGE</div>
        <div
            id="snapshot-price-yoy"
            class="text-2xl font-semibold {{ ($snapshot['rolling_12_price_yoy'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}"
        >
            {{ number_format($snapshot['rolling_12_price_yoy'] ?? 0, 1) }}%
        </div>
    </div>

    <div class="rounded bg-white p-4 text-center shadow">
        <div class="text-xs uppercase text-gray-500">SALE VOLUME CHANGE</div>
        <div
            id="snapshot-sales-yoy"
            class="text-2xl font-semibold {{ ($snapshot['rolling_12_sales_yoy'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}"
        >
            {{ number_format($snapshot['rolling_12_sales_yoy'] ?? 0, 1) }}%
        </div>
    </div>
</div>
