@extends('layouts.app')

@section('content')
@php
    $stats = $regime === 'england_wales' ? ($england_wales ?? []) : ($scotland ?? []);
    $ratingDistribution = $stats['rating_distribution'] ?? ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0];
    $potentialRatingDistribution = $stats['potential_rating_distribution'] ?? ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0];
    $environmentRatingDistribution = $stats['environment_rating_distribution'] ?? ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0];
    $potentialEnvironmentRatingDistribution = $stats['potential_environment_rating_distribution'] ?? ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0];
    $displayRegime = $regime === 'england_wales' ? 'England & Wales' : 'Scotland';
    $ratingLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    $ratingCounts = array_map(fn ($rating) => (int) ($ratingDistribution[$rating] ?? 0), $ratingLabels);
    $potentialRatingCounts = array_map(fn ($rating) => (int) ($potentialRatingDistribution[$rating] ?? 0), $ratingLabels);
    $environmentRatingCounts = array_map(fn ($rating) => (int) ($environmentRatingDistribution[$rating] ?? 0), $ratingLabels);
    $potentialEnvironmentRatingCounts = array_map(fn ($rating) => (int) ($potentialEnvironmentRatingDistribution[$rating] ?? 0), $ratingLabels);
    $ratingColors = ['#0f7a2e', '#2f9b31', '#a3d221', '#f4ea00', '#f2c100', '#e67e22', '#e00024'];
    $environmentRatingColors = ['#cfe2f8', '#9fc2e7', '#7aa5d9', '#4f82c3', '#b3b3b3', '#9b9b9b', '#7f7f7f'];
    $formatUkDate = function ($value): string {
        if ($value === null || trim((string) $value) === '') {
            return 'N/A';
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
@endphp

<div class="mx-auto max-w-7xl px-4 py-8">
    <section class="relative z-0 overflow-hidden rounded-lg border border-zinc-200 bg-white/80 p-6 md:p-8 shadow-sm mb-6 flex flex-col md:flex-row justify-between items-center">
        <div class="max-w-4xl">
            <h1 class="text-2xl md:text-2xl font-semibold tracking-tight text-zinc-900">
                EPC (Energy Performance Certificate) data for postcode {{ $postcode }}
            </h1>
            <p class="mt-2 text-sm leading-6 text-zinc-700">
                Location: <span class="font-semibold">{{ $displayRegime }}</span>
            </p>
            <p class="mt-1 text-sm leading-6 text-zinc-700">
                Total certificates: <span class="font-semibold">{{ $stats['total_certificates'] ?? 0 }}</span>
            </p>
            <p class="text-sm text-zinc-600 mt-2">This page provides all of the currently valid EPC certificates for the postcode <span class="text-lime-600 font-bold">{{ $postcode }}</span> in {{ $displayRegime }}.
                An EPC certificate is valid for 10 years from the date of inspection, so this page may include certificates that were inspected up to 10 years ago. 
                The data is sourced from the official EPC registers and is updated regularly.
            </p>
        <p class="mt-2 text-sm text-zinc-700">
            Earliest inspection date: {{ $formatUkDate($stats['inspection_dates']['earliest'] ?? null) }}
        </p>
        <p class="mt-1 text-sm text-zinc-700">
            Latest inspection date: {{ $formatUkDate($stats['inspection_dates']['latest'] ?? null) }}
        </p>
        </div>
        <div class="mt-6 md:mt-0 md:ml-8 flex-shrink-0">
            <img src="{{ asset('assets/images/site/epc.jpg') }}" alt="EPC Dashboard" class="w-72 h-auto">
        </div>
    </section>

    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Current EPC Rating Distribution</h2>
            <p class="mt-1 text-sm text-zinc-700">Count of certificates by current EPC rating band (A-G).</p>
            <div class="mt-4 h-72">
                <canvas id="ratingDistributionChart" class="h-full w-full"></canvas>
            </div>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Potential EPC Rating Distribution</h2>
            <p class="mt-1 text-sm text-zinc-700">Projected rating if recommended improvements in the EPC report are completed.</p>
            <div class="mt-4 h-72">
                <canvas id="potentialRatingDistributionChart" class="h-full w-full"></canvas>
            </div>
        </section>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Current Environmental Rating Distribution</h2>
            <p class="mt-1 text-sm text-zinc-700">Environmental impact values grouped into EPC-style A-G bands.</p>
            <div class="mt-4 h-72">
                <canvas id="environmentRatingDistributionChart" class="h-full w-full"></canvas>
            </div>
        </section>

        <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-zinc-900">Potential Environmental Rating Distribution</h2>
            <p class="mt-1 text-sm text-zinc-700">Projected environmental impact bands after recommended improvements.</p>
            <div class="mt-4 h-72">
                <canvas id="potentialEnvironmentRatingDistributionChart" class="h-full w-full"></canvas>
            </div>
        </section>
    </div>

    <section class="mb-6 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-zinc-900">EPC Certificates</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-3 py-2 text-left border-b">Inspection Date</th>
                        <th class="px-3 py-2 text-left border-b">Address</th>
                        <th class="px-3 py-2 text-left border-b">EPC (actual)</th>
                        <th class="px-3 py-2 text-left border-b">EPC (potential)</th>
                        <th class="px-3 py-2 text-left border-b">View</th>
                    </tr>
                </thead>
                <tbody>
                @foreach (($certificates ?? []) as $certificate)
                    @php
                        $identifier = $certificate['identifier'] ?? null;
                        $certificateUrl = null;
                        if ($identifier) {
                            $certificateUrl = $regime === 'scotland'
                                ? route('epc.scotland.show', ['rrn' => $identifier], false)
                                : route('epc.show', ['lmk' => $identifier], false);
                        } elseif (!empty($certificate['url'])) {
                            $certificateUrl = $certificate['url'];
                        }
                    @endphp
                    <tr class="odd:bg-white even:bg-zinc-50">
                        <td class="px-3 py-2 align-middle border-b">
                            {{ $formatUkDate($certificate['inspection_date'] ?? null) }}
                        </td>
                        <td class="px-3 py-2 align-middle border-b">
                            {{ $certificate['address'] ?? '' }}
                        </td>
                        <td class="px-3 py-2 align-middle border-b">
                            {{ $certificate['rating'] ?? '' }}
                        </td>
                        <td class="px-3 py-2 align-middle border-b">
                            {{ $certificate['potential_rating'] ?? '' }}
                        </td>
                        <td class="px-3 py-2 align-middle border-b text-center">
                            @if (!empty($certificateUrl))
                                <a
                                    href="{{ $certificateUrl }}"
                                    class="inline-flex items-center justify-center gap-1 text-lime-700 hover:text-lime-900"
                                    aria-label="View EPC report for {{ $certificate['address'] ?? '' }}"
                                    title="View report"
                                >
                                    <svg class="inline-block h-[2em] w-[2em] leading-none align-middle pt-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M11 3a8 8 0 1 0 5.026 14.225l4.374 4.374a.75.75 0 1 0 1.06-1.06l-4.374-4.375A8 8 0 0 0 11 3Zm-6.5 8a6.5 6.5 0 1 1 13 0 6.5 6.5 0 0 1-13 0Z" clip-rule="evenodd"/>
                                        <path d="M8.25 10.5a.75.75 0 0 1 .75-.75h3.19l-1.22-1.22a.75.75 0 1 1 1.06-1.06l2.5 2.5a.75.75 0 0 1 0 1.06l-2.5 2.5a.75.75 0 0 1-1.06-1.06l1.22-1.22H9a.75.75 0 0 1-.75-.75Z"/>
                                    </svg>
                                    <span class="sr-only">View</span>
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            return;
        }

        const buildChart = (elementId, label, values, colors) => {
            const canvas = document.getElementById(elementId);
            if (!canvas) {
                return;
            }

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($ratingLabels),
                    datasets: [{
                        label: label,
                        data: values,
                        backgroundColor: colors,
                        borderColor: colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        };

        buildChart('ratingDistributionChart', 'Current EPC rating', @json($ratingCounts), @json($ratingColors));
        buildChart('potentialRatingDistributionChart', 'Potential EPC rating', @json($potentialRatingCounts), @json($ratingColors));
        buildChart('environmentRatingDistributionChart', 'Current environmental rating', @json($environmentRatingCounts), @json($environmentRatingColors));
        buildChart('potentialEnvironmentRatingDistributionChart', 'Potential environmental rating', @json($potentialEnvironmentRatingCounts), @json($environmentRatingColors));
    });
</script>
@endsection
