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

        DB::unprepared('DROP TRIGGER IF EXISTS bi_land_registry_set_yeardate ON land_registry;');
        DB::unprepared('DROP TRIGGER IF EXISTS bu_land_registry_set_yeardate ON land_registry;');
        DB::unprepared('DROP FUNCTION IF EXISTS set_land_registry_yeardate();');

        DB::unprepared(<<<'SQL'
CREATE FUNCTION set_land_registry_yeardate()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    NEW."YearDate" = CASE
        WHEN NEW."Date" IS NOT NULL THEN EXTRACT(YEAR FROM NEW."Date")::int
        ELSE NULL
    END;
    RETURN NEW;
END;
$$;
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER bi_land_registry_set_yeardate
BEFORE INSERT ON land_registry
FOR EACH ROW
EXECUTE FUNCTION set_land_registry_yeardate();
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER bu_land_registry_set_yeardate
BEFORE UPDATE OF "Date" ON land_registry
FOR EACH ROW
EXECUTE FUNCTION set_land_registry_yeardate();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS bi_land_registry_set_yeardate ON land_registry;');
        DB::unprepared('DROP TRIGGER IF EXISTS bu_land_registry_set_yeardate ON land_registry;');
        DB::unprepared('DROP FUNCTION IF EXISTS set_land_registry_yeardate();');
    }
};
