<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PropertyResearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transactions = collect($this->resource['results']);
        $first = $transactions->first();

        return [
            'slug' => $this->resource['slug'],
            'address' => $this->resource['address'],
            'property_type' => [
                'code' => $this->resource['propertyTypeCode'],
                'label' => $this->resource['propertyTypeLabel'],
            ],
            'location' => [
                'postcode' => $first?->Postcode,
                'latitude' => $this->resource['mapLat'],
                'longitude' => $this->resource['mapLong'],
                'approximate' => true,
            ],
            'transactions' => $transactions->map(fn (object $transaction): array => [
                'date' => $this->dateString($transaction->Date ?? null),
                'price' => isset($transaction->Price) ? (int) $transaction->Price : null,
                'property_type' => $transaction->PropertyType ?? null,
                'new_build' => $transaction->NewBuild ?? null,
                'tenure' => $transaction->Duration ?? null,
                'category' => $transaction->PPDCategoryType ?? null,
            ])->values(),
            'epc_certificates' => collect($this->resource['epcMatches'])
                ->filter(fn (array $match): bool => (float) ($match['score'] ?? 0) >= 80)
                ->map(function (array $match): array {
                    $certificate = $match['row'];

                    return [
                        'lmk_key' => $certificate->lmk_key ?? null,
                        'address' => $certificate->address ?? null,
                        'postcode' => $certificate->postcode ?? null,
                        'lodgement_date' => $certificate->lodgement_date ?? null,
                        'current_energy_rating' => $certificate->current_energy_rating ?? null,
                        'potential_energy_rating' => $certificate->potential_energy_rating ?? null,
                        'property_type' => $certificate->property_type ?? null,
                        'total_floor_area_square_metres' => isset($certificate->total_floor_area)
                            ? (float) $certificate->total_floor_area
                            : null,
                        'local_authority' => $certificate->local_authority_label ?? null,
                        'match_score' => round((float) $match['score'], 1),
                    ];
                })
                ->values(),
            'nearby_schools' => [
                'primary' => $this->schools($this->resource['nearbySchools']['primary']),
                'secondary' => $this->schools($this->resource['nearbySchools']['secondary']),
            ],
            'crime' => [
                'summary' => $this->resource['crimeSummary'],
                'direction' => $this->resource['crimeDirection'],
                'total_change_percent' => $this->resource['totalChange'],
                'top_increase' => $this->resource['topIncrease'],
                'top_decrease' => $this->resource['topDecrease'],
                'categories' => collect($this->resource['crimeData'])->values(),
                'trend' => collect($this->resource['crimeTrend'])->values(),
            ],
            'deprivation' => $this->resource['depr'],
            'deprivation_message' => $this->resource['deprMsg'],
            'council_tax_estimate' => $this->resource['councilTaxEstimate'],
            'market' => [
                'property_price_history' => $this->series($this->resource['priceHistory']),
                'postcode' => [
                    'price_history' => $this->series($this->resource['postcodePriceHistory']),
                    'sales_history' => $this->series($this->resource['postcodeSalesHistory']),
                ],
                'locality' => $this->marketArea(
                    $this->resource['localityPriceHistory'],
                    $this->resource['localitySalesHistory'],
                    $this->resource['localityPropertyTypes'],
                    $this->resource['localityAreaLink'],
                ),
                'town' => $this->marketArea(
                    $this->resource['townPriceHistory'],
                    $this->resource['townSalesHistory'],
                    $this->resource['townPropertyTypes'],
                    $this->resource['townAreaLink'],
                ),
                'district' => $this->marketArea(
                    $this->resource['districtPriceHistory'],
                    $this->resource['districtSalesHistory'],
                    $this->resource['districtPropertyTypes'],
                    $this->resource['districtAreaLink'],
                ),
                'county' => $this->marketArea(
                    $this->resource['countyPriceHistory'],
                    $this->resource['countySalesHistory'],
                    $this->resource['countyPropertyTypes'],
                    $this->resource['countyAreaLink'],
                ),
            ],
        ];
    }

    private function schools(Collection $schools): Collection
    {
        return $schools->map(fn (object $school): array => [
            'urn' => $school->urn ?? null,
            'name' => $school->establishment_name ?? null,
            'postcode' => $school->postcode ?? null,
            'phase' => $school->school_phase ?? null,
            'type' => $school->establishment_type ?? null,
            'age_range' => $school->age_range ?? null,
            'distance_miles' => isset($school->distance_miles) ? (float) $school->distance_miles : null,
            'latest_ofsted_rating' => $school->latest_ofsted_overall_effectiveness ?? null,
            'latest_inspection_date' => $school->latest_inspection_date ?? null,
            'url' => $school->url ?? null,
        ])->values();
    }

    private function series(Collection $series): Collection
    {
        return $series->map(fn (object $point): array => (array) $point)->values();
    }

    private function marketArea(
        Collection $priceHistory,
        Collection $salesHistory,
        Collection $propertyTypes,
        ?string $url,
    ): array {
        return [
            'price_history' => $this->series($priceHistory),
            'sales_history' => $this->series($salesHistory),
            'property_types' => $propertyTypes->values(),
            'url' => $url,
        ];
    }

    private function dateString(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return method_exists($date, 'toDateString') ? $date->toDateString() : (string) $date;
    }
}
