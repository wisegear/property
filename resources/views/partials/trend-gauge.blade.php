@php
    $trendGaugeValue = max(-100, min(100, (float) ($value ?? 0)));
    $invertTrendScale = (bool) ($invert ?? false);
    $gaugeVariant = $variant ?? 'default';
    $trendNeedleRotationValue = $trendGaugeValue * 0.9;
    $minimumVisibleRotation = 2.0;
    $negativeTrendColor = $invertTrendScale ? '#22c55e' : '#ef4444';
    $positiveTrendColor = $invertTrendScale ? '#ef4444' : '#22c55e';

    if ($trendGaugeValue !== 0.0 && abs($trendNeedleRotationValue) < $minimumVisibleRotation) {
        $trendNeedleRotationValue = $trendGaugeValue < 0 ? -$minimumVisibleRotation : $minimumVisibleRotation;
    }

    $trendNeedleRotation = number_format($trendNeedleRotationValue, 2, '.', '');
    $trendLinePosition = number_format(($trendGaugeValue + 100) / 2, 2, '.', '');
    $trendNeedleColor = match ($color ?? null) {
        'red' => '#dc2626',
        'yellow' => '#ca8a04',
        'green' => '#16a34a',
        'gray' => '#1f2937',
        default => match (true) {
            $trendGaugeValue < 0 => $negativeTrendColor,
            $trendGaugeValue > 0 => $positiveTrendColor,
            default => '#1f2937',
        },
    };
@endphp

<div @class([
    'flex shrink-0 items-center justify-center',
    'rounded-full' => $gaugeVariant !== 'line',
    'ml-3 h-11 w-11' => empty($wrapperClass),
    $wrapperClass ?? null,
]) title="{{ $title ?? '' }}" @if($gaugeVariant === 'line') role="img" aria-label="{{ $title ?? 'Trend' }}: {{ $leftLabel ?? 'Falling' }} to {{ $rightLabel ?? 'Rising' }}" @endif>
    @if ($gaugeVariant === 'line')
        <div class="w-full">
            <div class="relative">
                <div class="h-2.5 overflow-hidden rounded-full bg-linear-to-r {{ $invertTrendScale ? 'from-emerald-500 via-amber-400 to-rose-500' : 'from-rose-500 via-amber-400 to-emerald-500' }}"></div>
                <span class="absolute top-1/2 h-5 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-zinc-900 ring-2 ring-white" style="left: clamp(0.125rem, {{ $trendLinePosition }}%, calc(100% - 0.125rem))"></span>
            </div>
            <div class="mt-2 flex justify-between text-[11px] font-medium text-zinc-500">
                <span>{{ $leftLabel ?? 'Falling' }}</span>
                <span>{{ $middleLabel ?? 'No change' }}</span>
                <span>{{ $rightLabel ?? 'Rising' }}</span>
            </div>
        </div>
    @elseif ($gaugeVariant === 'market-status')
        <svg class="{{ $svgClass ?? 'h-7 w-11' }}" viewBox="0 0 120 70" aria-hidden="true">
            <path d="M 12 60 A 48 48 0 0 1 36 18"
                  fill="none"
                  stroke="{{ $invertTrendScale ? '#22c55e' : '#ef4444' }}"
                  stroke-width="12"
                  stroke-linecap="butt" />
            <path d="M 40 16 A 48 48 0 0 1 80 16"
                  fill="none"
                  stroke="#facc15"
                  stroke-width="12"
                  stroke-linecap="butt" />
            <path d="M 84 18 A 48 48 0 0 1 108 60"
                  fill="none"
                  stroke="{{ $invertTrendScale ? '#ef4444' : '#22c55e' }}"
                  stroke-width="12"
                  stroke-linecap="butt" />
            <path d="M 38 17 L 42 25" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" />
            <path d="M 82 17 L 78 25" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" />
            <g transform="rotate({{ $trendNeedleRotation }}, 60, 60)">
                <line x1="60" y1="60" x2="60" y2="24" stroke="#241c27" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="60" cy="60" r="4.5" fill="#241c27" />
                <circle cx="60" cy="60" r="2" fill="#ffffff" />
            </g>
        </svg>
    @elseif ($gaugeVariant === 'stress')
        <svg class="{{ $svgClass ?? 'h-7 w-11' }}" viewBox="0 0 120 70" aria-hidden="true">
            <path d="M 12 60 A 48 48 0 0 1 58 12"
                  fill="none"
                  stroke="#f97316"
                  stroke-width="12"
                  stroke-linecap="butt" />
            <path d="M 62 12 A 48 48 0 0 1 108 60"
                  fill="none"
                  stroke="#65a30d"
                  stroke-width="12"
                  stroke-linecap="butt" />
            <path d="M 15 60 A 45 45 0 0 1 58 15"
                  fill="none"
                  stroke="#ea580c"
                  stroke-width="6"
                  stroke-linecap="butt" />
            <path d="M 62 15 A 45 45 0 0 1 105 60"
                  fill="none"
                  stroke="#5faa1f"
                  stroke-width="6"
                  stroke-linecap="butt" />
            <path d="M 60 12 L 60 21" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" />
            <g transform="rotate({{ $trendNeedleRotation }}, 60, 60)">
                <line x1="60" y1="60" x2="60" y2="24" stroke="#241c27" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="60" cy="60" r="4.5" fill="#241c27" />
                <circle cx="60" cy="60" r="2" fill="#ffffff" />
            </g>
        </svg>
    @elseif ($gaugeVariant === 'dashboard-dual')
        <svg class="{{ $svgClass ?? 'h-7 w-11' }}" viewBox="0 0 120 70" aria-hidden="true">
            <path d="M 12 60 A 48 48 0 0 1 58 12"
                  fill="none"
                  stroke="{{ $negativeTrendColor }}"
                  stroke-width="12"
                  stroke-linecap="butt" />
            <path d="M 62 12 A 48 48 0 0 1 108 60"
                  fill="none"
                  stroke="{{ $positiveTrendColor }}"
                  stroke-width="12"
                  stroke-linecap="butt" />

            <path d="M 15 60 A 45 45 0 0 1 58 15"
                  fill="none"
                  stroke="{{ $invertTrendScale ? '#16a34a' : '#ea580c' }}"
                  stroke-width="6"
                  stroke-linecap="butt" />
            <path d="M 62 15 A 45 45 0 0 1 105 60"
                  fill="none"
                  stroke="{{ $invertTrendScale ? '#dc2626' : '#5faa1f' }}"
                  stroke-width="6"
                  stroke-linecap="butt" />

            <path d="M 60 12 L 60 21" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" />

            <g transform="rotate({{ $trendNeedleRotation }}, 60, 60)">
                <line x1="60" y1="60" x2="60" y2="24" stroke="#241c27" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="60" cy="60" r="4.5" fill="#241c27" />
                <circle cx="60" cy="60" r="2" fill="#ffffff" />
            </g>
        </svg>
    @else
        <svg class="{{ $svgClass ?? 'h-7 w-11' }}" viewBox="0 0 120 70" aria-hidden="true">
            <path d="M 12 60 A 48 48 0 0 1 56 12.2"
                  fill="none"
                  stroke="{{ $negativeTrendColor }}"
                  stroke-width="12"
                  stroke-linecap="round" />
            <path d="M 64 12.2 A 48 48 0 0 1 108 60"
                  fill="none"
                  stroke="{{ $positiveTrendColor }}"
                  stroke-width="12"
                  stroke-linecap="round" />
            <line x1="60" y1="10" x2="60" y2="18" stroke="#ffffff" stroke-width="3" stroke-linecap="round" />
            <g transform="rotate({{ $trendNeedleRotation }}, 60, 60)">
                <line x1="60" y1="60" x2="60" y2="12" stroke="{{ $trendNeedleColor }}" stroke-width="3.5" stroke-linecap="round" />
                <circle cx="60" cy="60" r="4.5" fill="{{ $trendNeedleColor }}" />
            </g>
        </svg>
    @endif
</div>
