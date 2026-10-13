@props(['decile' => null, 'label' => false])

@php
    $value = (int) ($decile ?? 0);
    $colour = match (true) {
        $value >= 1 && $value <= 3 => 'bg-rose-50 text-rose-800 ring-rose-200',
        $value >= 4 && $value <= 6 => 'bg-amber-50 text-amber-800 ring-amber-200',
        $value >= 7 && $value <= 10 => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        default => 'bg-zinc-50 text-zinc-600 ring-zinc-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex min-w-8 items-center justify-center gap-1 whitespace-nowrap rounded-md px-2 py-1 text-xs font-semibold leading-4 tabular-nums ring-1 ring-inset', $colour]) }}>
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        @if($label)<span class="font-medium">Decile:</span>@endif
        {{ $value >= 1 && $value <= 10 ? $value : 'N/A' }}
    @endif
</span>
