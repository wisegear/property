@props([
    'value',
    'label',
    'detail',
    'tone' => 'neutral',
    'icon' => 'circle',
])

@php
    $toneClasses = match ($tone) {
        'positive' => [
            'value' => 'text-lime-700',
            'detail' => 'text-lime-700',
            'icon' => 'bg-lime-50 text-lime-700 ring-1 ring-lime-200',
        ],
        'warning' => [
            'value' => 'text-yellow-500',
            'detail' => 'text-yellow-500',
            'icon' => 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-300',
        ],
        'negative' => [
            'value' => 'text-red-700',
            'detail' => 'text-red-700',
            'icon' => 'bg-red-50 text-red-700 ring-1 ring-red-200',
        ],
        default => [
            'value' => 'text-yellow-700',
            'detail' => 'text-yellow-700',
            'icon' => 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-300',
        ],
    };
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex items-center justify-between gap-3">
        <div class="text-3xl font-bold tracking-tight {{ $toneClasses['value'] }}">{{ $value }}</div>
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $toneClasses['icon'] }}">
            @switch($icon)
                @case('trend-down')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M4 7h7v7"></path>
                        <path d="m20 17-9-9-7 7"></path>
                    </svg>
                    @break
                @case('trend-up')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M20 7h-7v7"></path>
                        <path d="m4 17 9-9 7 7"></path>
                    </svg>
                    @break
                @case('home')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M3 11.5 12 4l9 7.5"></path>
                        <path d="M5 10.5V20h14v-9.5"></path>
                        <path d="M9.5 20v-5h5v5"></path>
                    </svg>
                    @break
                @case('alert')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                        <path d="M10.3 4.6 2.9 17.2A1.2 1.2 0 0 0 4 19h16a1.2 1.2 0 0 0 1.1-1.8L13.7 4.6a1.95 1.95 0 0 0-3.4 0Z"></path>
                    </svg>
                    @break
                @default
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="12" cy="12" r="8"></circle>
                    </svg>
            @endswitch
        </div>
    </div>

    <div class="mt-2 text-sm font-medium text-slate-600">{{ $label }}</div>
    <div class="mt-4 text-sm font-semibold {{ $toneClasses['detail'] }}">{{ $detail }}</div>
</div>
