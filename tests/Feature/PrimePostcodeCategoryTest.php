<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrimePostcodeCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_outer_prime_london_category_can_be_inserted(): void
    {
        DB::table('prime_postcodes')->insert([
            'postcode' => 'SW11',
            'category' => 'Outer Prime London',
        ]);

        $this->assertDatabaseHas('prime_postcodes', [
            'postcode' => 'SW11',
            'category' => 'Outer Prime London',
        ]);
    }

    public function test_legacy_categories_still_can_be_inserted(): void
    {
        DB::table('prime_postcodes')->insert([
            'postcode' => 'W8',
            'category' => 'Prime Central',
        ]);

        DB::table('prime_postcodes')->insert([
            'postcode' => 'SW1X',
            'category' => 'Ultra Prime',
        ]);

        $this->assertDatabaseHas('prime_postcodes', [
            'postcode' => 'W8',
            'category' => 'Prime Central',
        ]);

        $this->assertDatabaseHas('prime_postcodes', [
            'postcode' => 'SW1X',
            'category' => 'Ultra Prime',
        ]);
    }
}
