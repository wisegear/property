@props([
    'value',
    'label',
    'detail',
    'tone' => 'neutral',
    'icon' => 'circle',
    'gaugeValue' => null,
    'gaugeVariant' => 'dashboard-dual',
    'invertGauge' => false,
])

@php
    $toneClasses = match ($tone) {
        'positive' => [
            'value' => 'text-lime-700',
            'detail' => 'text-lime-700',
            'accent' => 'bg-lime-500',
        ],
        'warning' => [
            'value' => 'text-amber-600',
            'detail' => 'text-amber-700',
            'accent' => 'bg-amber-400',
        ],
        'negative' => [
            'value' => 'text-red-700',
            'detail' => 'text-red-700',
            'accent' => 'bg-red-500',
        ],
        default => [
            'value' => 'text-slate-900',
            'detail' => 'text-slate-600',
            'accent' => 'bg-slate-400',
        ],
    };
@endphp

<div class="bg-white p-5">
    <div class="text-sm font-semibold leading-5 text-slate-700">{{ $label }}</div>
    <div class="mt-3 text-2xl font-bold tracking-tight {{ $toneClasses['value'] }}">{{ $value }}</div>
    <div class="mt-2 text-sm font-semibold {{ $toneClasses['detail'] }}">{{ $detail }}</div>
    <div class="mt-4 h-0.5 w-12 {{ $toneClasses['accent'] }}" aria-hidden="true"></div>
</div>
