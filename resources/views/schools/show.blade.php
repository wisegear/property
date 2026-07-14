@extends('layouts.app')

@section('title', $school->establishment_name.': Ofsted and School Details')
@section('description', 'View '.$school->establishment_name.' details, latest Ofsted rating, map location and key school information.')

@section('meta')
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:title" content="{{ $school->establishment_name }}: Ofsted and School Details">
    <meta property="og:description" content="View {{ $school->establishment_name }} details, latest Ofsted rating, map location and key school information.">
    @php
        $breadcrumbItems = [
            ['name' => 'Schools', 'url' => route('schools.index')],
        ];

        if ($school->phaseLabel) {
            $breadcrumbItems[] = ['name' => $school->phaseLabel, 'url' => null];
        }

        if ($school->la_name) {
            $breadcrumbItems[] = ['name' => $school->la_name, 'url' => null];
        }

        $breadcrumbItems[] = ['name' => $school->establishment_name, 'url' => null];

        $schoolSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'School',
            'name' => $school->establishment_name,
            'url' => $canonicalUrl,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $school->address,
                'postalCode' => $school->postcode,
                'addressLocality' => $school->town,
                'addressRegion' => $school->county_name,
                'addressCountry' => 'GB',
            ],
            'telephone' => $school->telephone_num,
        ];

        if ($coordinates) {
            $schoolSchema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $coordinates['lat'],
                'longitude' => $coordinates['lng'],
            ];
        }

        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($breadcrumbItems)->values()->map(function ($item, $index) use ($canonicalUrl, $breadcrumbItems) {
                $schemaItem = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                ];

                $schemaItem['item'] = $item['url'] ?? ($index === count($breadcrumbItems) - 1 ? $canonicalUrl : null);

                return $schemaItem;
            })->all(),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($schoolSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <section class="mt-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-lg md:p-8">
        <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    @foreach($breadcrumbItems as $index => $breadcrumbItem)
                        @if($index > 0)
                            <span class="text-sm text-zinc-400">/</span>
                        @endif
                        @if($breadcrumbItem['url'])
                            <a href="{{ $breadcrumbItem['url'] }}" class="text-sm font-medium text-lime-700 hover:text-lime-900">{{ $breadcrumbItem['name'] }}</a>
                        @else
                            <span class="text-sm text-zinc-500">{{ $breadcrumbItem['name'] }}</span>
                        @endif
                    @endforeach
                </div>
                <h1 class="break-words text-2xl font-semibold text-zinc-700 md:text-3xl">{{ $school->establishment_name }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-zinc-600">
                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $school->ofstedRating->badgeClass }}">
                        {{ $school->ofstedRating->label }}
                    </span>
                </div>
                <div class="mt-3 space-y-1 text-sm leading-6 text-zinc-700">
                    <p>
                        @if($school->phaseLabel)
                            <span>{{ $school->phaseLabel }}</span>
                        @endif
                        @if($school->ageRange)
                            <span class="text-zinc-300">·</span>
                            <span>Ages {{ $school->ageRange }}</span>
                        @endif
                    </p>
                    <p>
                        @if($school->type_of_establishment_name)
                            <span>{{ $school->type_of_establishment_name }}</span>
                        @endif
                        @if($school->pupilCountLabel)
                            <span class="text-zinc-300">·</span>
                            <span>{{ $school->pupilCountLabel }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <dl class="grid shrink-0 grid-cols-2 gap-4 rounded border border-zinc-200 bg-zinc-50 p-4 text-sm md:min-w-80">
                <div>
                    <dt class="text-zinc-500">Postcode</dt>
                    <dd class="font-semibold text-zinc-800">{{ $school->postcode ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Capacity</dt>
                    <dd class="font-semibold text-zinc-800">{{ $school->school_capacity ? number_format($school->school_capacity) : 'N/A' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-zinc-500">Opened</dt>
                    <dd class="font-semibold text-zinc-800">{{ $school->openingDateLabel ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded border border-zinc-200 bg-white p-4 shadow-lg lg:col-span-2">
            <h2 class="mb-4 text-lg font-bold text-zinc-600">School details</h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                @foreach([
                    'Address' => $school->address,
                    'Postcode' => $school->postcode,
                    'Telephone' => $school->telephone_num,
                    'Website' => $school->websiteUrl,
                    'Headteacher' => trim(collect([$school->head_title_name, $school->head_first_name, $school->head_last_name])->filter()->join(' ')),
                    'Local authority' => $school->la_name,
                    'Religious character' => $school->religious_character_name,
                    'Admissions policy' => $school->admissions_policy_name,
                    'Gender' => $school->gender_name,
                    'Boarding status' => $school->boarders_name,
                    'Trust' => $school->trusts_name ?: $school->multi_academy_trust_name,
                    'Academy sponsor' => $school->school_sponsors_name ?: $school->academy_sponsor_name,
                    'Number of pupils' => $school->number_of_pupils ? number_format($school->number_of_pupils) : null,
                    'Capacity' => $school->school_capacity ? number_format($school->school_capacity) : null,
                    'Percentage full' => $school->capacityPercentage ? $school->capacityPercentage.'%' : null,
                ] as $label => $value)
                    @if($value)
                        <div class="{{ $label === 'Address' || $label === 'Website' ? 'sm:col-span-2' : '' }}">
                            <dt class="font-medium text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-0.5 break-words text-zinc-800">
                                @if($label === 'Website')
                                    <a href="{{ $value }}" target="_blank" rel="noopener noreferrer" class="text-lime-700 hover:text-lime-900 hover:underline">{{ $value }}</a>
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </section>

        <section class="rounded border border-zinc-200 bg-white p-4 shadow-lg">
            <h2 class="mb-4 text-lg font-bold text-zinc-600">Ofsted</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-zinc-500">Latest overall effectiveness</dt>
                    <dd class="mt-1">
                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $school->ofstedRating->badgeClass }}">
                            {{ $school->ofstedRating->label }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-zinc-500">Inspection date</dt>
                    <dd class="text-zinc-800">{{ $school->inspectionDateLabel ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-zinc-500">Inspection type</dt>
                    <dd class="text-zinc-800">{{ $school->latest_inspection_type ?? $school->latest_inspection_type_grouping ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-zinc-500">Inspection outcome</dt>
                    <dd class="text-zinc-800">{{ $school->latest_inspection_outcome ?? $school->ungraded_inspection_overall_outcome ?? 'N/A' }}</dd>
                </div>
            </dl>
            @if($school->reportUrl)
                <a href="{{ $school->reportUrl }}" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex w-full items-center justify-center rounded bg-zinc-700 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-500">
                    View latest Ofsted report
                </a>
                <p class="mt-2 text-xs text-zinc-500">Opens the official Ofsted inspection report.</p>
            @endif
            <p class="mt-5 border-t border-zinc-200 pt-3 text-xs leading-5 text-zinc-500">PropertyResearch does not publish or estimate school catchment boundaries. Admission depends on the school’s admissions policy and current availability.</p>
        </section>
    </div>

    @if($coordinates)
        <section class="rounded border border-zinc-200 bg-white p-4 shadow-lg">
            <h2 class="mb-3 text-lg font-bold text-zinc-600">Map</h2>
            <div id="school-map" class="h-80 w-full overflow-hidden rounded-md border border-zinc-200 bg-zinc-100"></div>
            <div class="mt-3 flex flex-col gap-2 text-sm sm:flex-row">
                <a href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded border border-zinc-300 bg-white px-3 py-2 font-medium text-zinc-700 hover:border-lime-300 hover:text-lime-800">
                    Get directions
                </a>
                <a href="{{ $googleMapsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded border border-zinc-300 bg-white px-3 py-2 font-medium text-zinc-700 hover:border-lime-300 hover:text-lime-800">
                    View on Google Maps
                </a>
            </div>
        </section>
    @elseif($school->address)
        <section class="rounded border border-zinc-200 bg-white p-4 shadow-lg">
            <h2 class="mb-3 text-lg font-bold text-zinc-600">Map</h2>
            <p class="text-sm text-zinc-600">Map coordinates are not available for this school.</p>
            <div class="mt-3 flex flex-col gap-2 text-sm sm:flex-row">
                <a href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded border border-zinc-300 bg-white px-3 py-2 font-medium text-zinc-700 hover:border-lime-300 hover:text-lime-800">
                    Get directions
                </a>
                <a href="{{ $googleMapsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded border border-zinc-300 bg-white px-3 py-2 font-medium text-zinc-700 hover:border-lime-300 hover:text-lime-800">
                    View on Google Maps
                </a>
            </div>
        </section>
    @endif

</div>

@if($coordinates)
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapEl = document.getElementById('school-map');
            if (!mapEl || typeof L === 'undefined') {
                return;
            }

            const lat = @json($coordinates['lat']);
            const lng = @json($coordinates['lng']);
            const map = L.map('school-map', { scrollWheelZoom: false }).setView([lat, lng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            L.marker([lat, lng]).addTo(map).bindPopup(@json($school->establishment_name)).openPopup();
        });
    </script>
@endif
@endsection
