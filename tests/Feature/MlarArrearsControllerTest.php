<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MlarArrearsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureMlarArrearsTableExists();
    }

    public function test_arrears_index_orders_quarters_and_selects_latest_period_on_postgres(): void
    {
        DB::table('mlar_arrears')->insert([
            ['band' => 'A', 'description' => 'In arrears', 'year' => 2024, 'quarter' => 'Q3', 'value' => 0.7],
            ['band' => 'A', 'description' => 'In arrears', 'year' => 2024, 'quarter' => 'Q1', 'value' => 0.4],
            ['band' => 'A', 'description' => 'In arrears', 'year' => 2024, 'quarter' => 'Q4', 'value' => 0.9],
            ['band' => 'A', 'description' => 'In arrears', 'year' => 2024, 'quarter' => 'Q2', 'value' => 0.6],
            ['band' => 'A', 'description' => 'In arrears', 'year' => 2025, 'quarter' => 'Q1', 'value' => 0.5],
            ['band' => 'A', 'description' => 'In arrears', 'year' => 2025, 'quarter' => 'Q4', 'value' => 1.0],
        ]);

        $response = $this->get(route('arrears.index', absolute: false));

        $response->assertOk();
        $response->assertSee('assets/images/site/arrears.jpg', false);
        $this->assertSame(
            ['2024 Q1', '2024 Q2', '2024 Q3', '2024 Q4', '2025 Q1', '2025 Q4'],
            $response->viewData('periods')->all()
        );
        $this->assertSame(2025, $response->viewData('latest')->year);
        $this->assertSame('Q4', $response->viewData('latest')->quarter);
    }

    protected function ensureMlarArrearsTableExists(): void
    {
        if (! Schema::hasTable('mlar_arrears')) {
            Schema::create('mlar_arrears', function (Blueprint $table) {
                $table->id();
                $table->string('band')->nullable();
                $table->string('description')->nullable();
                $table->unsignedInteger('year')->nullable();
                $table->string('quarter', 2)->nullable();
                $table->decimal('value', 8, 3)->nullable();
                $table->timestamps();
            });
        }
    }
}
