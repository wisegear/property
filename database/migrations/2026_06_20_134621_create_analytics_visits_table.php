<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_visits', function (Blueprint $table) {
            $table->id();
            $table->string('anon_visit_id', 36)->unique();
            $table->string('country_code', 2)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->text('referrer')->nullable();
            $table->text('landing_page')->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamp('first_seen_at')->index();
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE analytics_visits ADD COLUMN ip_address inet NULL');
        } else {
            Schema::table('analytics_visits', function (Blueprint $table): void {
                $table->string('ip_address', 64)->nullable();
            });
        }

        Schema::table('analytics_visits', function (Blueprint $table): void {
            $table->index('ip_address');
            $table->index(['is_bot', 'last_seen_at'], 'analytics_visits_bot_last_seen_idx');
            $table->index(['country_code', 'last_seen_at'], 'analytics_visits_country_last_seen_idx');
            $table->index(['ip_address', 'last_seen_at'], 'analytics_visits_ip_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_visits');
    }
};
