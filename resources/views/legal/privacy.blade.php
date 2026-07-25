@extends('legal.layout')

@section('title', 'PropertyResearch.uk App Privacy Policy')
@section('description', 'Privacy policy for the PropertyResearch.uk iOS app, including app and API data handling and Apple Maps disclosures.')
@section('legal-title', 'PropertyResearch.uk App Privacy Policy')
@section('legal-intro', 'Last updated 25 July 2026')

@section('legal-content')
    <section>
        <h2>Overview</h2>
        <p class="mt-3">The PropertyResearch.uk iOS app does not require a user account. The app does not contain advertising or third-party analytics SDKs. It is designed to return property research results without asking who owns, occupies or intends to purchase a searched property.</p>
    </section>

    <section>
        <h2>Information sent when you search</h2>
        <p class="mt-3">Postcodes, addresses and record references that you enter are transmitted securely to the PropertyResearch.uk API so that the requested results can be returned. A search term may identify a property, but the app does not ask whether you own it, occupy it or intend to purchase it.</p>
    </section>

    <section>
        <h2>Technical service information</h2>
        <p class="mt-3">The server and hosting or monitoring services may process normal technical request information, including an IP address, request time, device or software details supplied with a request, and technical error details. This is used to operate, secure, maintain and diagnose the service. It is not sold or used for advertising.</p>
        <p class="mt-3">Operational records are retained only for as long as reasonably necessary for security, reliability, diagnostics and legal obligations. Retention can vary according to the type of record and the systems involved.</p>
    </section>

    <section>
        <h2>This website and the app are different</h2>
        <p class="mt-3">The statement that the app has no third-party analytics SDKs applies to the installed iOS app. If you visit PropertyResearch.uk in a web browser, including this policy page, the website uses a first-party visitor cookie and Google Analytics to understand website use. Browser and hosting services may receive information such as page URLs, IP address, user agent and referral information. These website technologies are not embedded in the iOS app.</p>
    </section>

    <section>
        <h2>Apple Maps</h2>
        <p class="mt-3">When an EPC property map is displayed, the property address is sent to Apple Maps for geocoding. The app does not request or transmit your current location. Apple handles information under <a href="https://www.apple.com/legal/privacy/" target="_blank" rel="noopener noreferrer">Apple’s Privacy Policy <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>.</p>
    </section>

    <section>
        <h2>Your requests</h2>
        <p class="mt-3">You can contact PropertyResearch.uk through the <a href="{{ route('legal.support') }}">App Support page</a> with a privacy question or an access or deletion request. Please provide enough detail to identify the relevant request or operational record, if one can reasonably be identified.</p>
    </section>
@endsection
