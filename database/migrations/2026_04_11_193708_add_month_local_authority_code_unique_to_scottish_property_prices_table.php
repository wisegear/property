<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scottish_property_prices', function (Blueprint $table) {
            $table->unique(
                ['month', 'local_authority_code'],
                'scottish_property_prices_month_local_authority_code_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('scottish_property_prices', function (Blueprint $table) {
            $table->dropUnique('scottish_property_prices_month_local_authority_code_unique');
        });
    }
};
