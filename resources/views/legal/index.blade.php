@extends('legal.layout')

@section('title', 'Legal & Support')
@section('description', 'Privacy, data source, licensing, terms and support information for the PropertyResearch.uk iOS app.')
@section('legal-title', 'Legal & Support')
@section('legal-intro', 'Public information for users of the PropertyResearch.uk iOS app.')

@section('legal-content')
    <section class="grid gap-px overflow-hidden border border-zinc-200 bg-zinc-200 py-0! sm:grid-cols-2">
        @foreach([
            ['route' => 'legal.privacy', 'title' => 'App Privacy Policy', 'text' => 'How the app and API handle searches and technical information.'],
            ['route' => 'legal.data-sources', 'title' => 'Data Sources & Licensing', 'text' => 'Official sources, attribution and licensing notes.'],
            ['route' => 'legal.terms', 'title' => 'Terms & Disclaimers', 'text' => 'Important limits on research data, estimates and calculators.'],
            ['route' => 'legal.support', 'title' => 'App Support', 'text' => 'Request help, report incorrect data or make a privacy request.'],
        ] as $legalPage)
            <a href="{{ route($legalPage['route']) }}" class="group bg-white p-5 no-underline! transition hover:bg-zinc-50 md:p-6">
                <h2 class="flex items-center justify-between gap-4">{{ $legalPage['title'] }} <span aria-hidden="true" class="text-zinc-300 transition group-hover:translate-x-1 group-hover:text-lime-600">→</span></h2>
                <p class="mt-2 text-sm leading-6 text-zinc-600">{{ $legalPage['text'] }}</p>
            </a>
        @endforeach
    </section>
@endsection
