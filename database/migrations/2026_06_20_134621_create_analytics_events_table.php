<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('anon_visit_id', 36)->nullable()->index();
            $table->string('event_type', 50)->index();
            $table->string('event_key', 100)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE analytics_events ADD COLUMN ip_address inet NULL');
        } else {
            Schema::table('analytics_events', function (Blueprint $table): void {
                $table->string('ip_address', 64)->nullable();
            });
        }

        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->index('ip_address');
            $table->index(['anon_visit_id', 'created_at'], 'analytics_events_visit_created_idx');
            $table->index(['ip_address', 'created_at'], 'analytics_events_ip_created_idx');
            $table->index(['event_type', 'event_key', 'created_at'], 'analytics_events_type_key_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
