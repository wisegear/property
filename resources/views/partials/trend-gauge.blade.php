@php
    $trendGaugeValue = max(-100, min(100, (float) ($value ?? 0)));
    $trendNeedleRotation = number_format($trendGaugeValue * 0.9, 2, '.', '');
@endphp

<div @class([
    'ml-3 flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
    $wrapperClass ?? null,
]) title="{{ $title ?? '' }}">
    <svg class="{{ $svgClass ?? 'h-7 w-11' }}" viewBox="0 0 120 70" aria-hidden="true">
        <path d="M 12 60 A 48 48 0 0 1 60 12"
              fill="none"
              stroke="#ef4444"
              stroke-width="12"
              stroke-linecap="round" />
        <path d="M 60 12 A 48 48 0 0 1 108 60"
              fill="none"
              stroke="#22c55e"
              stroke-width="12"
              stroke-linecap="round" />
        <g transform="rotate({{ $trendNeedleRotation }}, 60, 60)">
            <line x1="60" y1="60" x2="60" y2="18" stroke="#1f2937" stroke-width="3" stroke-linecap="round" />
            <circle cx="60" cy="60" r="4" fill="#1f2937" />
        </g>
    </svg>
</div>
