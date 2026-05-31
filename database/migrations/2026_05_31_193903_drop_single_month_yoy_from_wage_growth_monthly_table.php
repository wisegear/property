<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wage_growth_monthly', function (Blueprint $table) {
            $table->dropColumn('single_month_yoy');
        });
    }

    public function down(): void
    {
        Schema::table('wage_growth_monthly', function (Blueprint $table) {
            $table->decimal('single_month_yoy', 5, 2)->nullable()->after('date');
        });
    }
};
