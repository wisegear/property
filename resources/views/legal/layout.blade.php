@extends('layouts.app')

@section('content')
    <section class="relative z-0 -mx-6 -mt-6 overflow-hidden border-b border-zinc-200 bg-white py-8 shadow-[0_1px_0_rgba(0,0,0,0.06)] md:py-9">
        <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[minmax(0,1fr)_minmax(280px,0.42fr)] md:gap-8">
            <div class="max-w-4xl">
                <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500"><span class="size-2 rounded-full bg-lime-500"></span>Legal and support</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 md:text-4xl">@yield('legal-title')</h1>
            @hasSection('legal-intro')
                    <p class="mt-3 max-w-3xl text-base leading-7 text-zinc-600">@yield('legal-intro')</p>
            @endif
            </div>
            <div class="hidden justify-self-end md:block">
                <img src="{{ asset('assets/images/site/about.jpg') }}" alt="Property research documents and data" class="h-44 w-full max-w-sm object-cover object-right [mask-image:linear-gradient(to_right,transparent,black_22%)]">
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
        <nav aria-label="Legal pages" class="mb-8 border border-zinc-200 bg-white shadow-sm">
            <ul class="flex flex-wrap divide-x divide-zinc-200 text-sm">
                <li><a class="block px-4 py-3 font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-lime-700" href="{{ route('legal.index') }}">Legal hub</a></li>
                <li><a class="block px-4 py-3 font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-lime-700" href="{{ route('legal.privacy') }}">App privacy</a></li>
                <li><a class="block px-4 py-3 font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-lime-700" href="{{ route('legal.data-sources') }}">Data sources</a></li>
                <li><a class="block px-4 py-3 font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-lime-700" href="{{ route('legal.terms') }}">Terms</a></li>
                <li><a class="block px-4 py-3 font-medium text-zinc-700 transition hover:bg-zinc-50 hover:text-lime-700" href="{{ route('legal.support') }}">Support</a></li>
            </ul>
        </nav>

        <div class="legal-content w-full space-y-0 border-t border-zinc-200 text-base leading-7 text-zinc-700
            [&_a]:font-medium [&_a]:text-lime-700 [&_a]:underline [&_a]:decoration-lime-300 [&_a]:underline-offset-4 hover:[&_a]:text-lime-900
            [&_h2]:text-xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-zinc-900
            [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-zinc-900
            [&_li]:pl-1 [&_section]:border-b [&_section]:border-zinc-200 [&_section]:py-7 [&_ul]:ml-5 [&_ul]:list-disc [&_ul]:space-y-2">
            @yield('legal-content')
        </div>
    </div>
@endsection
