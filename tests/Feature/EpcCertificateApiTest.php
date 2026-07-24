<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EpcCertificateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_structured_epc_certificate(): void
    {
        $this->ensureColumns();

        DB::table('epc_certificates')->insert([
            'LMK_KEY' => '8904-2404-6929-2796-0763',
            'ADDRESS' => '32, Laleham Court, Chobham Road',
            'POSTCODE' => 'GU21 4AX',
            'CURRENT_ENERGY_RATING' => 'C',
            'POTENTIAL_ENERGY_RATING' => 'B',
            'CURRENT_ENERGY_EFFICIENCY' => '72',
            'POTENTIAL_ENERGY_EFFICIENCY' => '84',
            'ENVIRONMENT_IMPACT_CURRENT' => '68',
            'ENVIRONMENT_IMPACT_POTENTIAL' => '81',
            'TOTAL_FLOOR_AREA' => '81',
            'PROPERTY_TYPE' => 'Flat',
            'WALLS_DESCRIPTION' => 'Cavity wall, filled cavity',
            'MAINHEAT_DESCRIPTION' => 'Boiler and radiators, mains gas',
            'LIGHTING_COST_CURRENT' => '80',
            'LIGHTING_COST_POTENTIAL' => '60',
        ]);

        $this->get('/api/v1/epc/8904-2404-6929-2796-0763')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.lmk_key', '8904-2404-6929-2796-0763')
            ->assertJsonPath('data.address.postcode', 'GU21 4AX')
            ->assertJsonPath('data.energy.current_rating', 'C')
            ->assertJsonPath('data.energy.current_efficiency', 72)
            ->assertJsonPath('data.environmental_impact.potential_score', 81)
            ->assertJsonPath('data.property.total_floor_area_square_metres', 81)
            ->assertJsonPath('data.construction.walls.description', 'Cavity wall, filled cavity')
            ->assertJsonPath('data.heating.main.description', 'Boiler and radiators, mains gas')
            ->assertJsonPath('data.estimated_costs.lighting.current', 80)
            ->assertJsonPath('data.recommendations', [])
            ->assertJsonStructure([
                'data' => [
                    'certificate',
                    'property',
                    'energy',
                    'environmental_impact',
                    'estimated_costs',
                    'construction',
                    'heating',
                    'lighting',
                    'renewables',
                    'recommendations',
                    'website_url',
                ],
            ]);
    }

    public function test_it_returns_json_not_found_for_an_unknown_certificate(): void
    {
        $this->ensureColumns();

        $this->getJson('/api/v1/epc/unknown-reference')
            ->assertNotFound()
            ->assertJsonPath('message', 'EPC certificate not found.');
    }

    private function ensureColumns(): void
    {
        $columns = [
            'LMK_KEY' => fn (Blueprint $table) => $table->string('LMK_KEY', 128)->nullable(),
            'ADDRESS' => fn (Blueprint $table) => $table->text('ADDRESS')->nullable(),
            'POSTCODE' => fn (Blueprint $table) => $table->string('POSTCODE', 16)->nullable(),
            'CURRENT_ENERGY_RATING' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_RATING', 8)->nullable(),
            'POTENTIAL_ENERGY_RATING' => fn (Blueprint $table) => $table->string('POTENTIAL_ENERGY_RATING', 8)->nullable(),
            'CURRENT_ENERGY_EFFICIENCY' => fn (Blueprint $table) => $table->string('CURRENT_ENERGY_EFFICIENCY', 32)->nullable(),
            'POTENTIAL_ENERGY_EFFICIENCY' => fn (Blueprint $table) => $table->string('POTENTIAL_ENERGY_EFFICIENCY', 32)->nullable(),
            'ENVIRONMENT_IMPACT_CURRENT' => fn (Blueprint $table) => $table->string('ENVIRONMENT_IMPACT_CURRENT')->nullable(),
            'ENVIRONMENT_IMPACT_POTENTIAL' => fn (Blueprint $table) => $table->string('ENVIRONMENT_IMPACT_POTENTIAL')->nullable(),
            'TOTAL_FLOOR_AREA' => fn (Blueprint $table) => $table->string('TOTAL_FLOOR_AREA', 64)->nullable(),
            'PROPERTY_TYPE' => fn (Blueprint $table) => $table->string('PROPERTY_TYPE')->nullable(),
            'WALLS_DESCRIPTION' => fn (Blueprint $table) => $table->text('WALLS_DESCRIPTION')->nullable(),
            'MAINHEAT_DESCRIPTION' => fn (Blueprint $table) => $table->text('MAINHEAT_DESCRIPTION')->nullable(),
            'LIGHTING_COST_CURRENT' => fn (Blueprint $table) => $table->string('LIGHTING_COST_CURRENT')->nullable(),
            'LIGHTING_COST_POTENTIAL' => fn (Blueprint $table) => $table->string('LIGHTING_COST_POTENTIAL')->nullable(),
        ];

        if (! Schema::hasTable('epc_certificates')) {
            Schema::create('epc_certificates', function (Blueprint $table) use ($columns): void {
                foreach ($columns as $definition) {
                    $definition($table);
                }
            });

            return;
        }

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('epc_certificates', $column)) {
                Schema::table('epc_certificates', function (Blueprint $table) use ($definition): void {
                    $definition($table);
                });
            }
        }
    }
}
