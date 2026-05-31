<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WageGrowthMonthly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminWageGrowthImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_wage_growth_csv_with_ons_style_month_labels(): void
    {
        $admin = $this->createAdminUser();
        $file = UploadedFile::fake()->createWithContent(
            'wage-growth.csv',
            "Unit,%\n2001 MAR,3.9\n2001 APR,4.5\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.wagegrowth.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Wage growth CSV imported successfully.');

        $marchRow = WageGrowthMonthly::query()->whereDate('date', '2001-03-01')->first();
        $aprilRow = WageGrowthMonthly::query()->whereDate('date', '2001-04-01')->first();

        $this->assertNotNull($marchRow);
        $this->assertSame(3.9, $marchRow->three_month_avg_yoy);
        $this->assertNotNull($aprilRow);
        $this->assertSame(4.5, $aprilRow->three_month_avg_yoy);
    }

    public function test_import_updates_existing_row_for_same_month(): void
    {
        $admin = $this->createAdminUser();

        WageGrowthMonthly::query()->create([
            'date' => '2001-03-01',
            'three_month_avg_yoy' => 3.1,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'wage-growth.csv',
            "Unit,%\n2001 MAR,3.9\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.wagegrowth.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();

        $this->assertSame(1, WageGrowthMonthly::query()->count());
        $marchRow = WageGrowthMonthly::query()->whereDate('date', '2001-03-01')->first();

        $this->assertNotNull($marchRow);
        $this->assertSame(3.9, $marchRow->three_month_avg_yoy);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();

        $roleId = DB::table('user_roles')->insertGetId([
            'name' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles_pivot')->insert([
            'role_id' => $roleId,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin;
    }
}
