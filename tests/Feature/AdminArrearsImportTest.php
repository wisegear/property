<?php

namespace Tests\Feature;

use App\Models\MlarArrear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminArrearsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_arrears_csv_with_year_header_row(): void
    {
        $admin = $this->createAdminUser();
        $file = UploadedFile::fake()->createWithContent(
            'arrears.csv',
            "description,2007,,, ,2008,,,\n,Q1,Q2,Q3,Q4,Q1,Q2,Q3,Q4\n1.5 < 2.5% in arrears,0.59,0.59,0.60,0.62,0.63,0.64,0.69,0.75\nIn possession,0.08,0.08,0.08,0.10,0.11,0.14,0.18,0.18\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.arrears.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Arrears CSV imported successfully.');

        $arrearsRow = MlarArrear::query()
            ->where('description', '1.5 < 2.5% in arrears')
            ->where('year', 2008)
            ->where('quarter', 'Q4')
            ->first();
        $repossessionRow = MlarArrear::query()
            ->where('description', 'In possession')
            ->where('year', 2007)
            ->where('quarter', 'Q1')
            ->first();

        $this->assertNotNull($arrearsRow);
        $this->assertSame('0.750', $arrearsRow->value);
        $this->assertNotNull($repossessionRow);
        $this->assertSame('0.080', $repossessionRow->value);
    }

    public function test_import_updates_existing_row_for_same_description_year_and_quarter(): void
    {
        $admin = $this->createAdminUser();

        MlarArrear::query()->create([
            'description' => 'In possession',
            'year' => 2007,
            'quarter' => 'Q1',
            'value' => 0.010,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'arrears.csv',
            "description,2007\n,Q1\nIn possession,0.08\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.arrears.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();

        $this->assertSame(1, MlarArrear::query()->count());
        $row = MlarArrear::query()
            ->where('description', 'In possession')
            ->where('year', 2007)
            ->where('quarter', 'Q1')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('0.080', $row->value);
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
