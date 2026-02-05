<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE land_registry ALTER COLUMN "TransactionID" TYPE CHAR(38)');
        DB::statement('ALTER TABLE land_registry ALTER COLUMN "TransactionID" SET NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE land_registry ALTER COLUMN "TransactionID" TYPE CHAR(36)');
        DB::statement('ALTER TABLE land_registry ALTER COLUMN "TransactionID" SET NOT NULL');
    }
};
