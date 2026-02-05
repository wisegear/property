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

        if (! Schema::hasTable('blog_post_tags')) {
            return;
        }

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('blog_post_tags', 'id') as seq");
        $sequenceName = $sequence?->seq;

        if ($sequenceName === null) {
            DB::statement('CREATE SEQUENCE blog_post_tags_id_seq OWNED BY blog_post_tags.id');
            DB::statement("ALTER TABLE blog_post_tags ALTER COLUMN id SET DEFAULT nextval('blog_post_tags_id_seq')");
            $sequenceName = 'blog_post_tags_id_seq';
        }

        DB::statement("SELECT setval('{$sequenceName}', (SELECT COALESCE(MAX(id), 1) FROM blog_post_tags))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('blog_post_tags')) {
            return;
        }

        DB::statement('ALTER TABLE blog_post_tags ALTER COLUMN id DROP DEFAULT');
    }
};
