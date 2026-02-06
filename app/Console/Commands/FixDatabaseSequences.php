<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixDatabaseSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:fix-sequences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset PostgreSQL sequences to match the current max IDs.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->warn('Skipping: sequence reset only applies to PostgreSQL.');

            return self::SUCCESS;
        }

        DB::statement(<<<'SQL'
DO $$
DECLARE
  r RECORD;
BEGIN
  FOR r IN
    SELECT
      quote_ident(pg_namespace.nspname) AS schema_name,
      quote_ident(pg_class.relname) AS table_name,
      quote_ident(pg_attribute.attname) AS column_name,
      pg_get_serial_sequence(pg_namespace.nspname || '.' || pg_class.relname, pg_attribute.attname) AS seq_name
    FROM pg_class
    JOIN pg_attribute
      ON pg_attribute.attrelid = pg_class.oid
    JOIN pg_namespace
      ON pg_namespace.oid = pg_class.relnamespace
    WHERE
      pg_attribute.attnum > 0
      AND NOT pg_attribute.attisdropped
      AND pg_class.relkind = 'r'
      AND pg_namespace.nspname = current_schema()
      AND pg_get_serial_sequence(pg_namespace.nspname || '.' || pg_class.relname, pg_attribute.attname) IS NOT NULL
  LOOP
    IF r.seq_name IS NULL OR to_regclass(r.seq_name) IS NULL THEN
      CONTINUE;
    END IF;

    EXECUTE format(
      'SELECT setval(%L, (SELECT COALESCE(MAX(%s), 1) FROM %s.%s), true);',
      r.seq_name,
      r.column_name,
      r.schema_name,
      r.table_name
    );
  END LOOP;
END$$;
SQL);

        $this->info('Sequences reset successfully.');

        return self::SUCCESS;
    }
}
