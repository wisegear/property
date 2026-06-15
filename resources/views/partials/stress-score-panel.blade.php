@php
    $totalStress = $totalStress ?? null;

    $isSticky = $isSticky ?? true;
    $showDashboardLink = $showDashboardLink ?? false;

    if (is_null($totalStress)) {
        $stressScore = null;
    } else {
        // Convert to 0–100 scale (max possible is 31: seven 4-point indicators plus arrears (0–3))
        $scaled = max(0, min(100, round(($totalStress / 31) * 100)));
        $stressScore = $scaled;

        // Determine stress level and styling
        if ($stressScore >= 70) {
            $stressLabel = 'High stress';
            $stressClass = 'text-rose-800 border-rose-200';
        } elseif ($stressScore >= 40) {
            $stressLabel = 'Elevated risk';
            $stressClass = 'text-amber-800 border-amber-200';
        } else {
            $stressLabel = 'Low stress';
            $stressClass = 'text-emerald-800 border-emerald-200';
        }

        $gaugeRotation = $stressScore <= 40
            ? -90 + pow(($stressScore / 40), 1.6) * 71.79
            : ($stressScore <= 69
                ? -18.21 + (($stressScore - 40) / 29) * 52.10
                : 33.89 + (($stressScore - 70) / 30) * 56.11);
    }
@endphp

@if(!is_null($stressScore))
    @php
        $panelClasses = 'mb-8 rounded-xl border border-gray-200 bg-gradient-to-br from-white to-gray-50 p-5 md:p-6 shadow-lg';
        if ($isSticky) {
            $panelClasses .= ' sticky top-0 z-40 backdrop-blur-sm bg-white/95';
        }
    @endphp
    <section class="{{ $panelClasses }}">
        <div class="flex flex-col gap-4 md:grid md:grid-cols-3 md:items-center">
            {{-- Left: Title and description --}}
            <div class="md:col-span-1">
                <h2 class="text-sm text-center font-semibold tracking-wide text-gray-700 uppercase">
                    Overall Property MArket Stress Index
                </h2>
                <p class="mt-1 text-sm text-center text-gray-700 md:block">
                    A single 0–100 score combining all eight indicators. Higher scores mean more stress and risk.
                </p>
                @if($showDashboardLink)
                    <div class="mt-3 flex justify-center">
                        <a href="/economic-dashboard"
                           class="inline-flex items-center gap-2 rounded-md border border-lime-600 px-3 py-1.5 text-xs font-semibold text-lime-700 transition hover:bg-lime-50">
                            View stress indicators
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                @endif
            </div>

            {{-- Center: Semi-circular gauge --}}
            <div class="flex flex-col items-center md:col-span-1">
                <div class="relative h-28 w-52">
                    <svg class="h-28 w-52" viewBox="0 0 220 130" aria-hidden="true">
                        <!-- Outer dial -->
                        <path d="M 24 110 A 86 86 0 0 1 75 34"
                              fill="none"
                              stroke="#65a30d"
                              stroke-width="28"
                              stroke-linecap="butt" />
                        <path d="M 78 31 A 86 86 0 0 1 142 31"
                              fill="none"
                              stroke="#facc15"
                              stroke-width="28"
                              stroke-linecap="butt" />
                        <path d="M 145 34 A 86 86 0 0 1 196 110"
                              fill="none"
                              stroke="#f97316"
                              stroke-width="28"
                              stroke-linecap="butt" />

                        <!-- Inner shading -->
                        <path d="M 31 110 A 79 79 0 0 1 77 40"
                              fill="none"
                              stroke="#5faa1f"
                              stroke-width="14"
                              stroke-linecap="butt" />
                        <path d="M 80 38 A 79 79 0 0 1 140 38"
                              fill="none"
                              stroke="#eab308"
                              stroke-width="14"
                              stroke-linecap="butt" />
                        <path d="M 143 40 A 79 79 0 0 1 189 110"
                              fill="none"
                              stroke="#ea580c"
                              stroke-width="14"
                              stroke-linecap="butt" />

                        <!-- Clean dividers -->
                        <path d="M 76 35 L 79 43" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />
                        <path d="M 144 35 L 141 43" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />

                        <!-- Needle -->
                        <g transform="rotate({{ $gaugeRotation }}, 110, 110)">
                            <line x1="110" y1="110" x2="110" y2="56"
                                  stroke="#241c27"
                                  stroke-width="4"
                                  stroke-linecap="round" />
                            <circle cx="110" cy="110" r="8" fill="#241c27" />
                            <circle cx="110" cy="110" r="3.5" fill="#ffffff" />
                        </g>
                    </svg>
                </div>
                <div class="flex items-baseline gap-1 -mt-2">
                    <span class="text-3xl md:text-4xl font-bold text-gray-900">{{ $stressScore }}</span>
                    <span class="text-xs text-gray-500">/ 100</span>
                </div>
                <span class="mt-1 rounded-md border bg-white px-3 py-1 text-[12px] font-medium {{ $stressClass }} whitespace-nowrap">
                    {{ $stressLabel }}
                </span>
            </div>

            {{-- Right: Score explanation --}}
            <div class="flex flex-col items-center text-right md:col-span-1">
                <div class="text-sm uppercase text-center tracking-wide text-gray-700 font-semibold mb-2">Stress Indicators Guide</div>
                <p class="text-xs text-center text-gray-600">
                    The score rolls up eight indicators into a 0–100 index. Under 40 is low stress,
                    40–69 signals elevated risk, and 70+ points to high stress. Use it to compare
                    momentum over time rather than a single-month snapshot.
                </p>
                <div class="text-xs text-gray-500 mt-2">Raw: {{ $totalStress }}/31</div>
            </div>
        </div>
    </section>
@endif
