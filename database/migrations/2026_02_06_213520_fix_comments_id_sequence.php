<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('comments')) {
            return;
        }

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('comments', 'id') as seq");
        $sequenceName = $sequence?->seq;

        if ($sequenceName === null) {
            DB::statement('CREATE SEQUENCE comments_id_seq OWNED BY comments.id');
            DB::statement("ALTER TABLE comments ALTER COLUMN id SET DEFAULT nextval('comments_id_seq')");
            $sequenceName = 'comments_id_seq';
        }

        DB::statement("SELECT setval('{$sequenceName}', (SELECT COALESCE(MAX(id), 1) FROM comments))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('comments')) {
            return;
        }

        DB::statement('ALTER TABLE comments ALTER COLUMN id DROP DEFAULT');
    }
};
