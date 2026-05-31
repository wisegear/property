<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unemployment_monthly', function (Blueprint $table) {
            $table->dropColumn('rate');
            $table->unsignedInteger('single_month')->nullable()->after('date');
            $table->decimal('single', 5, 2)->nullable()->after('single_month');
            $table->decimal('three_month', 5, 2)->nullable()->after('single');
        });
    }

    public function down(): void
    {
        Schema::table('unemployment_monthly', function (Blueprint $table) {
            $table->dropColumn(['single_month', 'single', 'three_month']);
            $table->decimal('rate', 4, 2)->nullable()->after('date');
        });
    }
};
