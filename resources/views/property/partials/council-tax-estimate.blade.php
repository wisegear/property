@if($councilTaxEstimate)
    <section class="h-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg" aria-labelledby="council-tax-estimate-heading">
        <div class="border-b border-zinc-200 bg-zinc-50 px-5 py-4 sm:px-6">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <h2 id="council-tax-estimate-heading" class="text-lg font-semibold text-zinc-700">Estimated Council Tax</h2>
                <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $councilTaxEstimate['financial_year'] }} estimate</span>
            </div>
        </div>

        <div class="grid gap-5 px-5 py-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
            <div class="flex flex-col gap-2">
                <p class="text-2xl font-bold tracking-tight text-zinc-800 sm:text-3xl">
                    £{{ number_format($councilTaxEstimate['low_annual']) }}@if($councilTaxEstimate['low_annual'] !== $councilTaxEstimate['high_annual'])–£{{ number_format($councilTaxEstimate['high_annual']) }}@endif
                    <span class="text-base font-medium text-zinc-500">per year</span>
                </p>
                <p class="text-sm font-semibold text-lime-700">
                    Likely {{ $councilTaxEstimate['band_label'] }} · standard two-adult charge
                </p>
            </div>

            <div class="rounded-md bg-zinc-100 px-4 py-3 text-sm text-zinc-600 sm:max-w-xs">
                Based on {{ $councilTaxEstimate['sales_used'] === 1 ? 'the recorded sale' : $councilTaxEstimate['sales_used'].' recorded sales' }}, adjusted to {{ $councilTaxEstimate['valuation_year'] }} values using house-price indices.
            </div>
        </div>

        <p class="border-t border-zinc-100 px-5 py-3 text-xs leading-relaxed text-zinc-500 sm:px-6">
            This is an estimate, not the property's official band or bill. It uses {{ $councilTaxEstimate['rate_basis'] }} Council Tax charges; the actual amount can vary by parish, local levy, discounts, premiums and exemptions.
        </p>

        @if($councilTaxEstimate['high_value_surcharge'] ?? null)
            @php($highValueSurcharge = $councilTaxEstimate['high_value_surcharge'])
            <div class="border-t border-zinc-200 px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <h3 class="text-sm font-semibold text-zinc-700">High Value Council Tax Surcharge</h3>
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">From April 2028</span>
                </div>

                <p class="mt-2 text-sm leading-relaxed text-zinc-600">
                    The latest recorded sale price of £{{ number_format($highValueSurcharge['latest_sale_price']) }} falls within the {{ $highValueSurcharge['band'] }} band of the forthcoming High Value Council Tax Surcharge. Based on that sale price, this would indicate an additional <strong class="font-semibold text-zinc-700">£{{ number_format($highValueSurcharge['amount']) }} per year</strong> from April 2028, on top of normal Council Tax.
                </p>
                <p class="mt-2 text-xs leading-relaxed text-zinc-500">
                    The actual surcharge will be determined using the property's 2026 Valuation Office valuation rather than its recorded sale price.
                </p>
            </div>
        @endif
    </section>
@endif
