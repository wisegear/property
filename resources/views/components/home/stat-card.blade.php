@props([
    'value',
    'label',
    'icon',
    'change' => null,
    'tone' => 'neutral',
])

@php
    $toneClasses = match ($tone) {
        'positive' => [
            'change' => 'text-emerald-700',
            'icon' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
        ],
        'improving' => [
            'change' => 'text-emerald-700',
            'icon' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
        ],
        'negative' => [
            'change' => 'text-orange-700',
            'icon' => 'bg-orange-50 text-orange-700 ring-1 ring-orange-100',
        ],
        default => [
            'change' => 'text-slate-900',
            'icon' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        ],
    };
@endphp

<div class="min-h-[132px] rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex items-center justify-between gap-3">
        <div class="text-[1.5rem] font-bold leading-none tracking-tight text-slate-950">{{ html_entity_decode($value) }}</div>
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $toneClasses['icon'] }}">
            @switch($icon)
                @case('database')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <ellipse cx="12" cy="5" rx="7" ry="3"></ellipse>
                        <path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5"></path>
                        <path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"></path>
                    </svg>
                    @break
                @case('file-search')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path>
                        <path d="M14 3v5h5"></path>
                        <circle cx="11" cy="14" r="2.5"></circle>
                        <path d="m13 16 2.5 2.5"></path>
                    </svg>
                    @break
                @case('home')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M3 11.5 12 4l9 7.5"></path>
                        <path d="M5 10.5V20h14v-9.5"></path>
                        <path d="M9.5 20v-5h5v5"></path>
                    </svg>
                    @break
                @case('key')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="8" cy="15" r="3.25"></circle>
                        <path d="M10.7 12.3 21 2"></path>
                        <path d="M15 5h4v4"></path>
                    </svg>
                    @break
                @case('percent')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M19 5 5 19"></path>
                        <circle cx="7.5" cy="7.5" r="2.5"></circle>
                        <circle cx="16.5" cy="16.5" r="2.5"></circle>
                    </svg>
                    @break
                @default
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="12" cy="12" r="8"></circle>
                    </svg>
            @endswitch
        </div>
    </div>

    <div class="mt-2 text-[0.95rem] leading-6 font-medium text-slate-600">{{ $label }}</div>

    @if($change)
        <div class="mt-3 text-[0.95rem] font-semibold {{ $toneClasses['change'] }}">{{ $change }}</div>
    @endif
</div>
