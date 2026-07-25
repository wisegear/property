@extends('legal.layout')

@section('title', 'Terms & Disclaimers')
@section('description', 'Terms and important disclaimers for use of the PropertyResearch.uk website and iOS app.')
@section('legal-title', 'Terms & Disclaimers')
@section('legal-intro', 'Please use PropertyResearch.uk as a research aid and verify information that matters to a decision.')

@section('legal-content')
    <section>
        <h2>General information only</h2>
        <p class="mt-3">The service is provided for general information and research. It does not provide financial, mortgage, investment, valuation, legal or tax advice. Important information should be verified with the official source and, where appropriate, an appropriately qualified professional.</p>
    </section>

    <section>
        <h2>Public records and estimates</h2>
        <p class="mt-3">Public records may be incomplete, delayed, provisional, corrected or withdrawn. PropertyResearch.uk cannot guarantee that every record is current, complete or error-free.</p>
        <ul class="mt-3">
            <li>School distances are estimates and do not establish catchment eligibility, admissions availability or travel distance.</li>
            <li>Crime information reflects reported and published records, not every incident.</li>
            <li>Geocoded map positions may be approximate.</li>
            <li>Mortgage and property-tax calculator results are illustrative estimates.</li>
            <li>Rates, thresholds, reliefs and personal circumstances may change a calculator result.</li>
        </ul>
    </section>

    <section>
        <h2>Service availability</h2>
        <p class="mt-3">Online features may be interrupted by maintenance, network failures or outages affecting hosting, mapping, registers and other external providers. Features and datasets may be changed, suspended or withdrawn as the service and source availability evolve.</p>
    </section>
@endsection
