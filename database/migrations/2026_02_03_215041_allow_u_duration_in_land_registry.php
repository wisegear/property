<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('land_registry') || ! Schema::hasColumn('land_registry', 'Duration')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
DO $$
DECLARE
    constraint_name text;
BEGIN
    FOR constraint_name IN
        SELECT c.conname
        FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        JOIN pg_attribute a ON a.attrelid = t.oid
        WHERE c.contype = 'c'
          AND n.nspname = current_schema()
          AND t.relname = 'land_registry'
          AND a.attname = 'Duration'
          AND a.attnum = ANY (c.conkey)
    LOOP
        EXECUTE format('ALTER TABLE land_registry DROP CONSTRAINT %I', constraint_name);
    END LOOP;
END $$;
SQL);

            DB::statement("ALTER TABLE land_registry ADD CONSTRAINT land_registry_duration_check CHECK (\"Duration\" IN ('F', 'L', 'U'))");

            return;
        }

        Schema::table('land_registry', function (Blueprint $table): void {
            $table->enum('Duration', ['F', 'L', 'U'])->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('land_registry') || ! Schema::hasColumn('land_registry', 'Duration')) {
            return;
        }

        DB::table('land_registry')
            ->where('Duration', 'U')
            ->update(['Duration' => null]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE land_registry DROP CONSTRAINT IF EXISTS land_registry_duration_check');
            DB::statement("ALTER TABLE land_registry ADD CONSTRAINT land_registry_duration_check CHECK (\"Duration\" IN ('F', 'L'))");

            return;
        }

        Schema::table('land_registry', function (Blueprint $table): void {
            $table->enum('Duration', ['F', 'L'])->nullable()->change();
        });
    }
};
