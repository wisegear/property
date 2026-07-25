<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SchoolResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $school = $this->resource['school'];
        $coordinates = $this->resource['coordinates'];

        return [
            'slug' => $this->resource['canonical_slug'],
            'urn' => $this->nullableString($school->urn ?? null),
            'name' => $this->nullableString($school->establishment_name ?? null),
            'phase' => $this->nullableString($school->phase_of_education_name ?? null),
            'establishment_type' => $this->nullableString($school->type_of_establishment_name ?? null),
            'age_range' => [
                'minimum' => isset($school->statutory_low_age) ? (int) $school->statutory_low_age : null,
                'maximum' => isset($school->statutory_high_age) ? (int) $school->statutory_high_age : null,
                'label' => $school->ageRange,
            ],
            'pupil_count' => isset($school->number_of_pupils)
                ? (int) $school->number_of_pupils
                : (isset($school->ofsted_total_number_of_pupils) ? (int) $school->ofsted_total_number_of_pupils : null),
            'capacity' => isset($school->school_capacity) ? (int) $school->school_capacity : null,
            'address' => $this->nullableString($school->address ?? null),
            'postcode' => $this->nullableString($school->postcode ?? null),
            'latitude' => $coordinates['lat'] ?? null,
            'longitude' => $coordinates['lng'] ?? null,
            'telephone' => $this->nullableString($school->telephone_num ?? null),
            'school_website' => $school->websiteUrl,
            'headteacher' => $this->headteacher($school),
            'local_authority' => $this->nullableString($school->la_name ?? null),
            'religious_character' => $this->nullableString($school->religious_character_name ?? null),
            'admissions_policy' => $this->nullableString($school->admissions_policy_name ?? null),
            'gender' => $this->nullableString($school->gender_name ?? null),
            'boarding_status' => $this->nullableString($school->boarders_name ?? null),
            'trust' => $this->firstString($school->trusts_name ?? null, $school->multi_academy_trust_name ?? null),
            'academy_sponsor' => $this->firstString($school->school_sponsors_name ?? null, $school->academy_sponsor_name ?? null),
            'opening_date' => $this->dateString($school->open_date ?? null),
            'current_ofsted_rating' => $this->ofstedRating($school->latest_ofsted_overall_effectiveness ?? null),
            'latest_inspection_date' => $this->dateString($school->latest_inspection_date ?? $school->inspection_start_date ?? null),
            'inspection_type' => $this->firstString(
                $school->latest_inspection_type ?? null,
                $school->latest_inspection_type_grouping ?? null,
                $school->inspection_type ?? null,
            ),
            'inspection_outcome' => $this->firstString(
                $school->latest_inspection_outcome ?? null,
                $school->ungraded_inspection_overall_outcome ?? null,
                $school->event_type_grouping ?? null,
            ),
            'ofsted_report_url' => $school->reportUrl,
            'local_property_market' => $this->resource['local_property_market'],
            'website_url' => route('schools.show', ['slug' => $this->resource['canonical_slug']]),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (($string = $this->nullableString($value)) !== null) {
                return $string;
            }
        }

        return null;
    }

    private function headteacher(object $school): ?string
    {
        $name = collect([
            $school->head_title_name ?? null,
            $school->head_first_name ?? null,
            $school->head_last_name ?? null,
        ])->map(fn (mixed $part): string => trim((string) $part))->filter()->join(' ');

        return $name !== '' ? $name : null;
    }

    private function dateString(mixed $date): ?string
    {
        if ($this->nullableString($date) === null) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ofstedRating(mixed $rating): ?string
    {
        return match (strtolower(trim((string) $rating))) {
            '1', 'outstanding' => 'Outstanding',
            '2', 'good' => 'Good',
            '3', 'requires improvement' => 'Requires improvement',
            '4', 'inadequate' => 'Inadequate',
            default => null,
        };
    }
}
