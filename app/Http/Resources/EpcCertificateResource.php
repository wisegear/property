<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpcCertificateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $scotland = $this->value('_epc_nation') === 'scotland';
        $reference = $scotland
            ? $this->value('REPORT_REFERENCE_NUMBER', 'report_reference_number')
            : ($this->value('LMK_KEY', 'lmk_key')
                ?? $this->value('BUILDING_REFERENCE_NUMBER', 'building_reference_number'));

        return [
            'reference' => $reference,
            'lmk_key' => $this->value('LMK_KEY', 'lmk_key'),
            'report_reference_number' => $this->value('REPORT_REFERENCE_NUMBER', 'report_reference_number'),
            'building_reference_number' => $this->value('BUILDING_REFERENCE_NUMBER', 'building_reference_number'),
            'uprn' => $this->value('UPRN', 'uprn'),
            'address' => [
                'display' => $this->value('ADDRESS', 'address') ?? $this->joinedAddress(),
                'line_1' => $this->value('ADDRESS1', 'address1'),
                'line_2' => $this->value('ADDRESS2', 'address2'),
                'line_3' => $this->value('ADDRESS3', 'address3'),
                'post_town' => $this->value('POSTTOWN', 'posttown'),
                'postcode' => $this->value('POSTCODE', 'postcode'),
                'local_authority' => $this->value('LOCAL_AUTHORITY_LABEL', 'local_authority_label'),
                'county' => $this->value('COUNTY', 'county'),
                'region' => $this->value('REGION', 'region'),
                'country' => $this->value('COUNTRY', 'country'),
            ],
            'certificate' => [
                'inspection_date' => $this->value('INSPECTION_DATE', 'inspection_date'),
                'lodgement_date' => $this->value('LODGEMENT_DATE', 'lodgement_date'),
                'lodgement_datetime' => $this->value('LODGEMENT_DATETIME', 'lodgement_datetime'),
                'transaction_type' => $this->value('TRANSACTION_TYPE', 'transaction_type'),
                'report_type' => $this->value('REPORT_TYPE', 'report_type'),
            ],
            'property' => [
                'type' => $this->value('PROPERTY_TYPE', 'property_type'),
                'built_form' => $this->value('BUILT_FORM', 'built_form'),
                'construction_age_band' => $this->value('CONSTRUCTION_AGE_BAND', 'construction_age_band'),
                'tenure' => $this->value('TENURE', 'tenure'),
                'total_floor_area_square_metres' => $this->number('TOTAL_FLOOR_AREA', 'total_floor_area'),
                'floor_level' => $this->value('FLOOR_LEVEL', 'floor_level'),
                'floor_height' => $this->number('FLOOR_HEIGHT', 'floor_height'),
                'flat_top_storey' => $this->value('FLAT_TOP_STOREY', 'flat_top_storey'),
                'flat_storey_count' => $this->integer('FLAT_STOREY_COUNT', 'flat_storey_count'),
                'habitable_rooms' => $this->integer('NUMBER_HABITABLE_ROOMS', 'number_habitable_rooms'),
                'heated_rooms' => $this->integer('NUMBER_HEATED_ROOMS', 'number_heated_rooms'),
                'extensions' => $this->integer('EXTENSION_COUNT', 'extension_count'),
                'open_fireplaces' => $this->integer('NUMBER_OPEN_FIREPLACES', 'number_open_fireplaces'),
            ],
            'energy' => [
                'current_rating' => $this->value('CURRENT_ENERGY_RATING', 'current_energy_rating'),
                'potential_rating' => $this->value('POTENTIAL_ENERGY_RATING', 'potential_energy_rating'),
                'current_efficiency' => $this->integer('CURRENT_ENERGY_EFFICIENCY', 'current_energy_efficiency'),
                'potential_efficiency' => $this->integer('POTENTIAL_ENERGY_EFFICIENCY', 'potential_energy_efficiency'),
                'current_consumption_kwh_per_square_metre' => $this->number('ENERGY_CONSUMPTION_CURRENT', 'energy_consumption_current'),
                'potential_consumption_kwh_per_square_metre' => $this->number('ENERGY_CONSUMPTION_POTENTIAL', 'energy_consumption_potential'),
                'tariff' => $this->value('ENERGY_TARIFF', 'energy_tariff'),
            ],
            'environmental_impact' => [
                'current_score' => $this->integer('ENVIRONMENT_IMPACT_CURRENT', 'environment_impact_current'),
                'potential_score' => $this->integer('ENVIRONMENT_IMPACT_POTENTIAL', 'environment_impact_potential'),
                'current_co2_emissions_tonnes' => $this->number('CO2_EMISSIONS_CURRENT', 'co2_emissions_current'),
                'potential_co2_emissions_tonnes' => $this->number('CO2_EMISSIONS_POTENTIAL', 'co2_emissions_potential'),
                'current_co2_per_square_metre' => $this->number('CO2_EMISS_CURR_PER_FLOOR_AREA', 'co2_emiss_curr_per_floor_area'),
            ],
            'estimated_costs' => [
                'lighting' => $this->currentPotential('LIGHTING_COST'),
                'heating' => $this->currentPotential('HEATING_COST'),
                'hot_water' => $this->currentPotential('HOT_WATER_COST'),
            ],
            'construction' => [
                'walls' => $scotland ? $this->component('WALL') : $this->component('WALLS'),
                'roof' => $this->component('ROOF'),
                'floor' => $this->component('FLOOR'),
                'windows' => $this->component('WINDOWS'),
                'glazed_type' => $this->value('GLAZED_TYPE', 'glazed_type'),
                'glazed_area' => $this->value('GLAZED_AREA', 'glazed_area'),
                'multi_glaze_proportion' => $this->number('MULTI_GLAZE_PROPORTION', 'multi_glaze_proportion'),
            ],
            'heating' => [
                'main' => $this->component('MAINHEAT'),
                'main_controls' => $this->component('MAINHEATC', 'MAINHEATCONT_DESCRIPTION'),
                'secondary' => $this->component('SHEATING', 'SECONDHEAT_DESCRIPTION'),
                'hot_water' => $this->component('HOT_WATER', 'HOTWATER_DESCRIPTION'),
                'main_fuel' => $this->value('MAIN_FUEL', 'main_fuel'),
                'mains_gas' => $this->value('MAINS_GAS_FLAG', 'mains_gas_flag'),
                'mechanical_ventilation' => $this->value('MECHANICAL_VENTILATION', 'mechanical_ventilation'),
            ],
            'lighting' => [
                ...$this->component('LIGHTING'),
                'low_energy_percentage' => $this->number('LOW_ENERGY_LIGHTING', 'low_energy_lighting'),
                'fixed_outlets' => $this->integer('FIXED_LIGHTING_OUTLETS_COUNT', 'fixed_lighting_outlets_count'),
                'low_energy_fixed_outlets' => $this->integer('LOW_ENERGY_FIXED_LIGHT_COUNT', 'low_energy_fixed_light_count'),
            ],
            'renewables' => [
                'photo_supply' => $this->value('PHOTO_SUPPLY', 'photo_supply'),
                'solar_water_heating' => $this->value('SOLAR_WATER_HEATING_FLAG', 'solar_water_heating_flag'),
                'wind_turbines' => $this->integer('WIND_TURBINE_COUNT', 'wind_turbine_count'),
            ],
            'recommendations' => [],
            'website_url' => $scotland
                ? route('epc.scotland.show', ['rrn' => $reference])
                : route('epc.show', ['lmk' => $reference]),
        ];
    }

    private function value(string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($this->resource->{$key}) && $this->resource->{$key} !== '') {
                return $this->resource->{$key};
            }
        }

        return null;
    }

    private function number(string ...$keys): ?float
    {
        $value = $this->value(...$keys);

        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(string ...$keys): ?int
    {
        $value = $this->value(...$keys);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array{current: float|null, potential: float|null}
     */
    private function currentPotential(string $prefix): array
    {
        return [
            'current' => $this->number($prefix.'_CURRENT', strtolower($prefix).'_current'),
            'potential' => $this->number($prefix.'_POTENTIAL', strtolower($prefix).'_potential'),
        ];
    }

    /**
     * @return array{description: mixed, energy_efficiency: mixed, environmental_efficiency: mixed}
     */
    private function component(string $prefix, ?string $descriptionKey = null): array
    {
        $descriptionKey ??= $prefix.'_DESCRIPTION';

        return [
            'description' => $this->value($descriptionKey, strtolower($descriptionKey)),
            'energy_efficiency' => $this->value($prefix.'_ENERGY_EFF', strtolower($prefix).'_energy_eff'),
            'environmental_efficiency' => $this->value($prefix.'_ENV_EFF', strtolower($prefix).'_env_eff'),
        ];
    }

    private function joinedAddress(): ?string
    {
        $address = implode(', ', array_filter([
            $this->value('ADDRESS1', 'address1'),
            $this->value('ADDRESS2', 'address2'),
            $this->value('ADDRESS3', 'address3'),
        ]));

        return $address !== '' ? $address : null;
    }
}
