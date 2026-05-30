<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epc_certificates', function (Blueprint $table): void {
            if (! Schema::hasColumn('epc_certificates', 'COUNTRY')) {
                $table->string('COUNTRY', 255)->nullable();
            }

            if (! Schema::hasColumn('epc_certificates', 'REGION')) {
                $table->string('REGION', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('epc_certificates', function (Blueprint $table): void {
            $columnsToDrop = [];

            if (Schema::hasColumn('epc_certificates', 'COUNTRY')) {
                $columnsToDrop[] = 'COUNTRY';
            }

            if (Schema::hasColumn('epc_certificates', 'REGION')) {
                $columnsToDrop[] = 'REGION';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
