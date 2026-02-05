<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostgresControllerRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('land_registry', 'YearDate')) {
            Schema::table('land_registry', function (Blueprint $table): void {
                $table->unsignedSmallInteger('YearDate')->nullable();
            });
        }
    }

    public function test_prime_london_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/property/prime-central-london')->assertOk();
    }

    public function test_outer_prime_london_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/property/outer-prime-london')->assertOk();
    }

    public function test_ultra_prime_london_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/property/ultra-prime-central-london')->assertOk();
    }

    public function test_new_old_route_loads_without_mysql_specific_sql(): void
    {
        $this->get('/new-old')->assertOk();
    }
}
