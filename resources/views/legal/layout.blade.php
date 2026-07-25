@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl">
        <header class="mb-8 border-b border-zinc-200 pb-6">
            <p class="mb-2 text-sm font-semibold uppercase tracking-wider text-lime-700">PropertyResearch.uk iOS app</p>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">@yield('legal-title')</h1>
            @hasSection('legal-intro')
                <p class="mt-4 max-w-3xl text-base leading-7 text-zinc-600">@yield('legal-intro')</p>
            @endif
        </header>

        <div class="legal-content space-y-8 text-base leading-7 text-zinc-700
            [&_a]:font-medium [&_a]:text-rose-700 [&_a]:underline [&_a]:decoration-rose-300 [&_a]:underline-offset-4 hover:[&_a]:text-rose-900
            [&_h2]:text-xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-zinc-900
            [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-zinc-900
            [&_li]:pl-1 [&_p]:max-w-3xl [&_ul]:ml-5 [&_ul]:list-disc [&_ul]:space-y-2">
            @yield('legal-content')
        </div>

        <nav aria-label="Legal pages" class="mt-12 border-t border-zinc-200 pt-6">
            <ul class="flex flex-wrap gap-x-5 gap-y-3 text-sm">
                <li><a class="font-medium text-zinc-700 hover:text-rose-700 hover:underline" href="{{ route('legal.index') }}">Legal hub</a></li>
                <li><a class="font-medium text-zinc-700 hover:text-rose-700 hover:underline" href="{{ route('legal.privacy') }}">App Privacy Policy</a></li>
                <li><a class="font-medium text-zinc-700 hover:text-rose-700 hover:underline" href="{{ route('legal.data-sources') }}">Data Sources</a></li>
                <li><a class="font-medium text-zinc-700 hover:text-rose-700 hover:underline" href="{{ route('legal.terms') }}">Terms</a></li>
                <li><a class="font-medium text-zinc-700 hover:text-rose-700 hover:underline" href="{{ route('legal.support') }}">Support</a></li>
            </ul>
        </nav>
    </div>
@endsection
