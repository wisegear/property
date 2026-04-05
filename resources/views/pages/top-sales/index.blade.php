@extends('layouts.app')
@include('partials.chartjs-head')

@section('title', 'Top Property Sales')
@section('description', 'The highest value residential property sales recorded by the Land Registry.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
        <section class="relative z-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-8">
            @include('partials.hero-background')
            <div class="relative z-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-center">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-600">
                        <span class="h-2 w-2 rounded-full bg-lime-500"></span>
                        Top Property Sales
                    </div>
                    <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl md:text-4xl">
                        {{ $modeConfig['title'] }}
                    </h1>

                    <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-600">
                        {{ $modeConfig['description'] }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3 text-xs font-medium text-zinc-600">
                        <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1">
                            Last warmed:
                            {{ $lastWarmedAt?->format('d M Y H:i') ?? 'Not warmed yet' }}
                        </span>
                    </div>
                </div>

                <div class="relative z-10 mt-2 flex justify-center lg:mt-0 lg:ml-8 lg:justify-end">
                    <img src="{{ asset('/assets/images/site/property-insghts.jpg') }}" alt="Property market insights" class="h-auto w-full max-w-[15rem] sm:max-w-xs lg:max-w-sm">
                </div>
            </div>
        </section>

        <section class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex rounded-lg border border-zinc-200 bg-white p-1 shadow-sm">
                @foreach ($modeOptions as $optionMode => $optionConfig)
                    <a href="{{ route('top-sales.index', ['mode' => $optionMode], false) }}"
                       class="rounded-md px-3 py-2 text-sm font-medium {{ $mode === $optionMode ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' }}">
                        {{ $optionConfig['label'] }}
                    </a>
                @endforeach
            </div>

            <p class="text-sm text-zinc-500 font-semibold">
                Click on any address to view more details about that property.
            </p>
        </section>

        @if ($topSale)
            @php
                $topSaleAddress = collect([
                    $topSale->PAON ?? null,
                    $topSale->SAON ?? null,
                    $topSale->Street ?? null,
                ])->filter()->implode(', ');

                $topSaleLocation = collect([
                    $topSale->TownCity ?? null,
                    $topSale->County ?? null,
                ])->filter()->implode(', ');
            @endphp

            <section class="mt-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-zinc-500">Most Expensive Sale</p>
                <p class="mt-2 text-3xl font-bold text-zinc-900">
                    £{{ number_format((int) ($topSale->Price ?? 0)) }}
                </p>
                <a href="{{ route('property.show.slug', ['slug' => $topSale->property_slug], false) }}"
                   class="mt-2 inline-flex text-lg font-medium text-lime-700 hover:underline">
                    {{ $topSaleAddress !== '' ? $topSaleAddress : ($topSale->Postcode ?? 'Unknown address') }}
                </a>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $topSale->Date ? \Illuminate\Support\Carbon::parse($topSale->Date)->format('d M Y') : 'Unknown date' }}
                    @if ($topSaleLocation !== '')
                        &bull; {{ $topSaleLocation }}
                    @endif
                </p>
            </section>
        @endif

        <section class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ($topThree as $sale)
                @php
                    $cardAddress = collect([
                        $sale->PAON ?? null,
                        $sale->SAON ?? null,
                        $sale->Street ?? null,
                    ])->filter()->implode(', ');
                @endphp

                <article class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-zinc-500">Next Highest Sale</p>
                    <p class="mt-1 text-xl font-semibold text-zinc-900">
                        £{{ number_format((int) ($sale->Price ?? 0)) }}
                    </p>
                    <a href="{{ route('property.show.slug', ['slug' => $sale->property_slug], false) }}"
                       class="mt-1 block text-sm font-medium text-lime-700 hover:underline">
                        {{ $cardAddress !== '' ? $cardAddress : ($sale->Postcode ?? 'Unknown address') }}
                    </a>
                    <p class="mt-1 text-xs text-zinc-500">{{ $sale->TownCity ?? 'Unknown town' }}</p>
                </article>
            @endforeach
        </section>

        @if ($salesScatter->isNotEmpty())
            <section class="mt-6 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500">Price History View</p>
                        <h2 class="mt-2 text-xl font-semibold text-zinc-900">Sale prices across the years shown in this table</h2>
                    </div>
                    <p class="text-sm text-zinc-500">Each point is a listed sale on this page, plotted by year and price.</p>
                </div>

                <div class="mt-5 h-72 min-w-0 sm:h-80">
                    <canvas id="top-sales-scatter-chart"></canvas>
                </div>
            </section>
        @endif

        <p class="mt-6 text-sm text-zinc-600">{{ $insight }}</p>

        <div class="mt-6 overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-zinc-600">Address</th>
                        <th class="px-4 py-3 text-left font-semibold text-zinc-600">Price</th>
                        <th class="px-4 py-3 text-left font-semibold text-zinc-600">Date</th>
                        <th class="px-4 py-3 text-left font-semibold text-zinc-600">Location</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($sales as $sale)
                        @php
                            $addressParts = array_filter([
                                $sale->PAON ?? null,
                                $sale->SAON ?? null,
                                $sale->Street ?? null,
                            ]);

                            $address = implode(', ', $addressParts);
                        @endphp

                        <tr class="hover:bg-zinc-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('property.show.slug', ['slug' => $sale->property_slug], false) }}"
                                   class="font-medium text-lime-700 hover:underline">
                                    {{ $address !== '' ? $address : ($sale->Postcode ?? 'Unknown address') }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-semibold text-zinc-900">
                                £{{ number_format((int) ($sale->Price ?? 0)) }}
                            </td>
                            <td class="px-4 py-3 text-zinc-600">
                                {{ $sale->Date ? \Illuminate\Support\Carbon::parse($sale->Date)->format('d M Y') : 'Unknown date' }}
                            </td>
                            <td class="px-4 py-3 text-zinc-600">
                                {{ collect([$sale->TownCity ?? null, $sale->County ?? null])->filter()->implode(', ') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-zinc-500">
                                No qualifying property sales are available yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sales->hasPages())
            <div class="mt-4">
                {{ $sales->onEachSide(1)->links() }}
            </div>
        @endif
    </div>

    @if ($salesScatter->isNotEmpty())
        <script>
            const topSalesScatterData = @json($salesScatter);

            new Chart(document.getElementById('top-sales-scatter-chart'), {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Sale price',
                        data: topSalesScatterData,
                        backgroundColor: 'rgba(101, 163, 13, 0.75)',
                        borderColor: '#3f6212',
                        borderWidth: 1,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    const point = context.raw ?? {};

                                    return `${point.address ?? 'Sale'}: £${Number(point.y ?? 0).toLocaleString()} (${point.date ?? ''})`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            type: 'linear',
                            ticks: {
                                precision: 0,
                                callback(value) {
                                    return Number(value).toFixed(0);
                                },
                            },
                            title: {
                                display: true,
                                text: 'Year',
                            },
                        },
                        y: {
                            ticks: {
                                callback(value) {
                                    return `£${Number(value).toLocaleString()}`;
                                },
                            },
                            title: {
                                display: true,
                                text: 'Sale price',
                            },
                        },
                    },
                },
            });
        </script>
    @endif
@endsection
