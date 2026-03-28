<?php

namespace Tests\Unit;

use App\Models\Crime;
use PHPUnit\Framework\TestCase;

class CrimeModelTest extends TestCase
{
    public function test_crime_model_uses_expected_table_fillable_and_casts(): void
    {
        $crime = new Crime;

        $this->assertSame('crime', $crime->getTable());
        $this->assertFalse($crime->usesTimestamps());
        $this->assertSame([
            'crime_id',
            'month',
            'reported_by',
            'falls_within',
            'longitude',
            'latitude',
            'location',
            'lsoa_code',
            'lsoa_name',
            'crime_type',
            'last_outcome_category',
            'context',
        ], $crime->getFillable());
        $this->assertSame('date', $crime->getCasts()['month']);
        $this->assertSame('decimal:7', $crime->getCasts()['longitude']);
        $this->assertSame('decimal:7', $crime->getCasts()['latitude']);
    }
}
