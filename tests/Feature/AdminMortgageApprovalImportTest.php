<?php

namespace Tests\Feature;

use App\Models\MortgageApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMortgageApprovalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_wide_csv_into_series_rows(): void
    {
        $admin = $this->createAdminUser();
        $file = UploadedFile::fake()->createWithContent(
            'mortgage-approvals.csv',
            "Date,LPMB3C8,LPMB4B3,LPMB4B4,LPMVTVX\n31-Mar-26,129924,51317,15077,63531\n28-Feb-26,119042,41222,15112,62708\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.approvals.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Mortgage approvals CSV imported successfully.');

        $housePurchase = MortgageApproval::query()
            ->where('series_code', 'LPMVTVX')
            ->whereDate('period', '2026-03-01')
            ->first();
        $totalApprovals = MortgageApproval::query()
            ->where('series_code', 'LPMB3C8')
            ->whereDate('period', '2026-02-01')
            ->first();

        $this->assertNotNull($housePurchase);
        $this->assertSame(63531, $housePurchase->value);
        $this->assertSame('count', $housePurchase->unit);
        $this->assertSame('BoE', $housePurchase->source);
        $this->assertNotNull($totalApprovals);
        $this->assertSame(119042, $totalApprovals->value);
    }

    public function test_import_updates_existing_row_for_same_series_and_month(): void
    {
        $admin = $this->createAdminUser();

        MortgageApproval::query()->create([
            'series_code' => 'LPMVTVX',
            'period' => '2026-03-01',
            'value' => 60000,
            'unit' => 'count',
            'source' => 'BoE',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'mortgage-approvals.csv',
            "Date,LPMVTVX\n31-Mar-26,63531\n"
        );

        $response = $this->actingAs($admin)->post(route('admin.approvals.import', absolute: false), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();

        $this->assertSame(1, MortgageApproval::query()->count());
        $updatedRow = MortgageApproval::query()
            ->where('series_code', 'LPMVTVX')
            ->whereDate('period', '2026-03-01')
            ->first();

        $this->assertNotNull($updatedRow);
        $this->assertSame(63531, $updatedRow->value);
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
