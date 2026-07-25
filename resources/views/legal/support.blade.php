@extends('legal.layout')

@section('title', 'App Support')
@section('description', 'Get help with the PropertyResearch.uk iOS app, report incorrect data, or make a privacy request.')
@section('legal-title', 'App Support')
@section('legal-intro', 'Get help with the app, report a data issue, or contact us about privacy.')

@section('legal-content')
    <section>
        <h2>How to contact us</h2>
        <p class="mt-3">Use the existing public <a href="https://wa.me/447720868799?text=Hi%20Lee%2C%20I%27m%20contacting%20you%20about%20the%20PropertyResearch.uk%20iOS%20app" target="_blank" rel="noopener noreferrer">PropertyResearch.uk WhatsApp contact <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a> or email <a href="mailto:lee@wisener.net">lee@wisener.net</a>. Tell us which app screen or property record is involved and what happened. Do not send passwords, payment information or other unnecessary sensitive information.</p>
        @auth
            <p class="mt-3">Website members can also <a href="{{ route('support.create') }}">open a support ticket</a>.</p>
        @endauth
    </section>

    <section>
        <h2>Incorrect data</h2>
        <p class="mt-3">Include the address, postcode or public record reference, the result you believe is incorrect, and a link to the official source if available. Source organisations may need to correct their own record before a change can flow through to PropertyResearch.uk.</p>
    </section>

    <section>
        <h2>Privacy requests</h2>
        <p class="mt-3">State whether your request concerns access, deletion or another privacy question. Include enough information to help locate the relevant request or operational record, but avoid sending unrelated personal information.</p>
    </section>

    <section>
        <h2>Useful links</h2>
        <ul class="mt-3">
            <li><a href="{{ route('legal.privacy') }}">PropertyResearch.uk App Privacy Policy</a></li>
            <li><a href="{{ route('legal.data-sources') }}">Data Sources &amp; Licensing</a></li>
            <li><a href="{{ route('legal.terms') }}">Terms &amp; Disclaimers</a></li>
            <li><a href="{{ route('home') }}">PropertyResearch.uk homepage</a></li>
        </ul>
    </section>
@endsection
