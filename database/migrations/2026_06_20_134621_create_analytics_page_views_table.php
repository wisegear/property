<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('anon_visit_id', 36)->index();
            $table->text('url');
            $table->string('route_name')->nullable()->index();
            $table->string('page_type')->nullable()->index();
            $table->timestamp('viewed_at')->index();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE analytics_page_views ADD COLUMN ip_address inet NULL');
        } else {
            Schema::table('analytics_page_views', function (Blueprint $table): void {
                $table->string('ip_address', 64)->nullable();
            });
        }

        Schema::table('analytics_page_views', function (Blueprint $table): void {
            $table->index('ip_address');
            $table->index(['anon_visit_id', 'viewed_at'], 'analytics_page_views_visit_viewed_idx');
            $table->index(['ip_address', 'viewed_at'], 'analytics_page_views_ip_viewed_idx');
            $table->index(['page_type', 'viewed_at'], 'analytics_page_views_page_type_viewed_idx');
            $table->index(['route_name', 'viewed_at'], 'analytics_page_views_route_viewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_page_views');
    }
};
