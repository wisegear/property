<?php

namespace Tests\Feature;

use App\Models\UnemploymentMonthly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUnemploymentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_unemployment_csv_with_short_month_dates(): void
    {
        $admin = $this->createAdminUser();
        $file = UploadedFile::fake()->createWithContent(
            'unemployment.csv',
            ",single_month,single,three_month\nJun-92,2938,10.1,9.9\nJul-92,2514,9.9,10.0\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.unemployment.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Unemployment CSV imported successfully.');

        $juneRow = UnemploymentMonthly::query()->whereDate('date', '1992-06-01')->first();
        $julyRow = UnemploymentMonthly::query()->whereDate('date', '1992-07-01')->first();

        $this->assertNotNull($juneRow);
        $this->assertSame(2938, $juneRow->single_month);
        $this->assertSame(10.1, $juneRow->single);
        $this->assertSame(9.9, $juneRow->three_month);
        $this->assertNotNull($julyRow);
        $this->assertSame(2514, $julyRow->single_month);
    }

    public function test_import_updates_existing_row_for_same_month(): void
    {
        $admin = $this->createAdminUser();

        UnemploymentMonthly::query()->create([
            'date' => '1992-06-01',
            'single_month' => 2900,
            'single' => 9.7,
            'three_month' => 9.5,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'unemployment.csv',
            ",single_month,single,three_month\nJun-92,2938,10.1,9.9\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.unemployment.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();

        $this->assertSame(1, UnemploymentMonthly::query()->count());
        $juneRow = UnemploymentMonthly::query()->whereDate('date', '1992-06-01')->first();

        $this->assertNotNull($juneRow);
        $this->assertSame(2938, $juneRow->single_month);
        $this->assertSame(10.1, $juneRow->single);
        $this->assertSame(9.9, $juneRow->three_month);
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
