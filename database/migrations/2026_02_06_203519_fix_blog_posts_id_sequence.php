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

        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('blog_posts', 'id') as seq");
        $sequenceName = $sequence?->seq;

        if ($sequenceName === null) {
            DB::statement('CREATE SEQUENCE blog_posts_id_seq OWNED BY blog_posts.id');
            DB::statement("ALTER TABLE blog_posts ALTER COLUMN id SET DEFAULT nextval('blog_posts_id_seq')");
            $sequenceName = 'blog_posts_id_seq';
        }

        DB::statement("SELECT setval('{$sequenceName}', (SELECT COALESCE(MAX(id), 1) FROM blog_posts))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('blog_posts')) {
            return;
        }

        DB::statement('ALTER TABLE blog_posts ALTER COLUMN id DROP DEFAULT');
    }
};
