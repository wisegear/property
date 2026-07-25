@extends('legal.layout')

@section('title', 'Data Sources & Licensing')
@section('description', 'Sources, licensing and attribution for property, EPC, school, crime and economic information used by PropertyResearch.uk.')
@section('legal-title', 'Data Sources & Licensing')
@section('legal-intro', 'The app combines records from multiple official sources. Coverage, update dates and licensing conditions differ by dataset.')

@section('legal-content')
    <aside class="rounded-lg border border-lime-300 bg-lime-50 p-5 text-zinc-900">
        <p class="font-semibold">Contains public sector information licensed under the <a href="https://www.nationalarchives.gov.uk/doc/open-government-licence/version/3/" target="_blank" rel="noopener noreferrer">Open Government Licence v3.0 <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>.</p>
    </aside>

    <section>
        <h2>Property sales and market data</h2>
        <p class="mt-3">Contains HM Land Registry data © Crown copyright and database right. HM Land Registry Price Paid Data is licensed under the Open Government Licence v3.0. See the <a href="https://www.gov.uk/government/collections/price-paid-data" target="_blank" rel="noopener noreferrer">HM Land Registry Price Paid Data collection <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>.</p>
        <p class="mt-3">UK House Price Index information and postcode and statistical geography lookups use official HM Land Registry and Office for National Statistics data, including ONS postcode directories. Scottish sale-price summaries use official Registers of Scotland and Scottish Government statistical releases where indicated.</p>
    </section>

    <section>
        <h2>Energy performance certificates</h2>
        <p class="mt-3">EPC information comes from the official registers for England and Wales and Scotland. Coverage, available fields, update schedules and licensing conditions can vary between registers and releases.</p>
        <ul class="mt-3">
            <li><a href="https://epc.opendatacommunities.org/" target="_blank" rel="noopener noreferrer">Energy Performance of Buildings Data: England and Wales <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a></li>
            <li><a href="https://www.scottishepcregister.org.uk/" target="_blank" rel="noopener noreferrer">Scottish Energy Performance Certificate Register <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a></li>
        </ul>
    </section>

    <section>
        <h2>Schools and inspections</h2>
        <p class="mt-3">School information is sourced from Department for Education public data, including <a href="https://get-information-schools.service.gov.uk/" target="_blank" rel="noopener noreferrer">Get Information about Schools <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>. Inspection information is sourced from <a href="https://reports.ofsted.gov.uk/" target="_blank" rel="noopener noreferrer">Ofsted <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a> where available.</p>
    </section>

    <section>
        <h2>Crime and economic indicators</h2>
        <p class="mt-3">Crime information is based on official <a href="https://data.police.uk/" target="_blank" rel="noopener noreferrer">police open data <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>. Market-stress and wider economic indicators use official releases including the <a href="https://www.bankofengland.co.uk/" target="_blank" rel="noopener noreferrer">Bank of England <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a> and the <a href="https://www.ons.gov.uk/" target="_blank" rel="noopener noreferrer">Office for National Statistics <span aria-hidden="true">↗</span><span class="sr-only"> (opens in a new tab)</span></a>. Repossession information also uses Ministry of Justice or HM Courts &amp; Tribunals Service releases where identified in the relevant feature.</p>
    </section>

    <section>
        <h2>Housing, rents, tax and deprivation</h2>
        <p class="mt-3">Housing stock, rental and property-tax reference data uses releases from the UK Government, Scottish Government, Welsh Government, Northern Ireland Statistics and Research Agency, the Valuation Office Agency and relevant revenue authorities where applicable. Deprivation features use the official English, Scottish, Welsh and Northern Irish indices for the stated edition. These datasets have their own terms and should not all be assumed to be licensed under the OGL.</p>
    </section>

    <section>
        <h2>PropertyResearch.uk processing</h2>
        <p class="mt-3">PropertyResearch.uk cleans, combines and summarises source records and produces independent calculations and visualisations. Derived outputs are not official publications of the source organisations. Links to third-party property portals or mapping services are provided for convenience and are not treated as source datasets.</p>
    </section>
@endsection
