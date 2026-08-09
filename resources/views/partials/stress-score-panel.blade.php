@php
    $totalStress = $totalStress ?? null;
    $isSticky = $isSticky ?? true;
    $showDashboardLink = $showDashboardLink ?? false;
    $stressScore = is_null($totalStress) ? null : max(0, min(100, round(($totalStress / 24) * 100)));

    if (! is_null($stressScore)) {
        if ($stressScore >= 70) {
            $stressLabel = 'High stress';
            $stressTone = 'text-rose-700 bg-rose-50 border-rose-200';
        } elseif ($stressScore >= 40) {
            $stressLabel = 'Elevated risk';
            $stressTone = 'text-amber-700 bg-amber-50 border-amber-200';
        } else {
            $stressLabel = 'Low stress';
            $stressTone = 'text-emerald-700 bg-emerald-50 border-emerald-200';
        }
    }
@endphp

@if(! is_null($stressScore))
    @php
        $panelClasses = 'rounded-sm border border-zinc-200 bg-white p-5 md:p-6';

        if ($isSticky) {
            $panelClasses .= ' sticky top-0 z-40 bg-white/95 backdrop-blur-sm';
        }
    @endphp

    <section class="{{ $panelClasses }}">
        <div class="grid gap-5 lg:grid-cols-2 lg:items-center">
            <div>
                <h2 class="text-lg font-bold text-zinc-900">Property Market Stress Index</h2>
                <p class="mt-1 text-sm leading-5 text-zinc-600">Eight housing and economic indicators combined into one current score.</p>
                @if($showDashboardLink)
                    <div class="mt-3 flex justify-start">
                        <a href="{{ route('economic.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-lime-700 hover:text-lime-800 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-lime-600 focus-visible:ring-offset-2">
                            View all indicators
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                @endif
            </div>

            <div>
                <div class="flex items-end justify-between gap-4">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-3xl font-bold tracking-tight text-zinc-900">{{ $stressScore }}</span>
                        <span class="text-sm text-zinc-500">/ 100</span>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $stressTone }}">{{ $stressLabel }}</span>
                </div>

                <div class="mt-4 w-full" role="img" aria-label="Property Market Stress Index score: {{ $stressScore }} out of 100, {{ $stressLabel }}">
                    <div class="relative">
                        <div class="h-2.5 overflow-hidden rounded-full bg-linear-to-r from-emerald-500 from-0% via-amber-400 via-55% to-rose-500 to-100%"></div>
                        <span class="absolute top-1/2 h-5 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-900 ring-2 ring-white" style="left: clamp(0.125rem, {{ $stressScore }}%, calc(100% - 0.125rem))"></span>
                    </div>
                    <div class="mt-2 flex justify-between text-[11px] font-medium text-zinc-500">
                        <span>Low</span>
                        <span>Elevated</span>
                        <span>High</span>
                    </div>
                </div>
            </div>

        </div>
    </section>
@endif
