<?php

namespace App\Http\Resources;

use App\Support\PropertyResearch\OfstedRating;
use App\Support\PropertyResearch\SchoolSlug;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class SchoolPostcodeSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'postcode' => $this->resource['postcode'],
            'latitude' => $this->resource['latitude'],
            'longitude' => $this->resource['longitude'],
            'primary' => $this->schools($this->resource['primary']),
            'secondary' => $this->schools($this->resource['secondary']),
        ];
    }

    /**
     * @param  Collection<int, object>  $schools
     * @return Collection<int, array<string, mixed>>
     */
    private function schools(Collection $schools): Collection
    {
        return $schools->map(function (object $school): array {
            $slug = SchoolSlug::for(
                (string) ($school->establishment_name ?? ''),
                $school->urn ?? null,
            );
            $rating = $school->latest_ofsted_overall_effectiveness ?? null;

            return [
                'urn' => isset($school->urn) ? (string) $school->urn : null,
                'name' => $school->establishment_name ?? null,
                'slug' => $slug,
                'postcode' => $school->postcode ?? null,
                'phase' => $school->school_phase ?? null,
                'establishment_type' => $school->establishment_type ?? null,
                'age_range' => $school->age_range ?? null,
                'distance_miles' => isset($school->distance_miles) ? (float) $school->distance_miles : null,
                'current_ofsted_rating' => $rating !== null ? OfstedRating::from($rating)->label : null,
                'latest_inspection_date' => $school->latest_inspection_date ?? null,
                'api_url' => route('api.v1.schools.show', ['slug' => $slug]),
                'website_url' => route('schools.show', ['slug' => $slug]),
            ];
        })->values();
    }
}
