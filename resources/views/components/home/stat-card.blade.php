@props([
    'value',
    'label',
    'icon',
])

@php
    $iconClasses = 'text-emerald-700';
@endphp

<div class="flex min-h-[124px] flex-col justify-center bg-white p-5">
    <div class="flex items-center justify-between gap-3">
        <div class="text-[1.5rem] font-bold leading-none tracking-tight text-slate-950">{{ html_entity_decode($value) }}</div>
        <div class="flex h-7 w-7 shrink-0 items-center justify-center {{ $iconClasses }}">
            @switch($icon)
                @case('database')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <ellipse cx="12" cy="5" rx="7" ry="3"></ellipse>
                        <path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5"></path>
                        <path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"></path>
                    </svg>
                    @break
                @case('file-search')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path>
                        <path d="M14 3v5h5"></path>
                        <circle cx="11" cy="14" r="2.5"></circle>
                        <path d="m13 16 2.5 2.5"></path>
                    </svg>
                    @break
                @case('home')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M3 11.5 12 4l9 7.5"></path>
                        <path d="M5 10.5V20h14v-9.5"></path>
                        <path d="M9.5 20v-5h5v5"></path>
                    </svg>
                    @break
                @case('key')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="8" cy="15" r="3.25"></circle>
                        <path d="M10.7 12.3 21 2"></path>
                        <path d="M15 5h4v4"></path>
                    </svg>
                    @break
                @case('percent')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M19 5 5 19"></path>
                        <circle cx="7.5" cy="7.5" r="2.5"></circle>
                        <circle cx="16.5" cy="16.5" r="2.5"></circle>
                    </svg>
                    @break
                @default
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <circle cx="12" cy="12" r="8"></circle>
                    </svg>
            @endswitch
        </div>
    </div>

    <div class="mt-2 text-[0.95rem] leading-6 font-medium text-slate-600">{{ $label }}</div>
</div>
