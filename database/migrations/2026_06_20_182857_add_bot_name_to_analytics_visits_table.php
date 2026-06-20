<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('analytics_visits', 'is_bot')) {
            Schema::table('analytics_visits', function (Blueprint $table): void {
                $table->boolean('is_bot')->default(false)->index();
            });
        }

        if (! Schema::hasColumn('analytics_visits', 'bot_name')) {
            Schema::table('analytics_visits', function (Blueprint $table): void {
                $table->string('bot_name')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('analytics_visits', 'bot_name')) {
            Schema::table('analytics_visits', function (Blueprint $table): void {
                $table->dropIndex(['bot_name']);
                $table->dropColumn('bot_name');
            });
        }
    }
};
