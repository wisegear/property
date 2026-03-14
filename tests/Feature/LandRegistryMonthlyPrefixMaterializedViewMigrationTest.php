<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LandRegistryMonthlyPrefixMaterializedViewMigrationTest extends TestCase
{
    #[Test]
    public function follow_up_migration_uses_full_outward_postcode_code_instead_of_fixed_three_character_prefix(): void
    {
        $contents = file_get_contents(
            base_path('database/migrations/2026_03_14_195745_fix_land_registry_monthly_prefix_mv_postcode_prefix.php')
        );
        $upMethod = explode('public function down(): void', (string) $contents)[0] ?? '';

        $this->assertIsString($contents);
        $this->assertIsString($upMethod);
        $this->assertStringContainsString(
            'SUBSTRING(REPLACE(UPPER("Postcode"), \' \', \'\') FROM \'^[A-Z]{1,2}[0-9][0-9A-Z]?\') AS postcode_prefix',
            $upMethod
        );
        $this->assertStringNotContainsString('LEFT(REPLACE("Postcode", \' \', \'\'), 3) AS postcode_prefix', $upMethod);
    }
}
