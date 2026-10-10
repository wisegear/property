<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scottish_property_prices', function (Blueprint $table): void {
            $table->renameColumn('median_residential_property_price', 'median');
            $table->renameColumn('volume_of_residential_property_sales', 'volume');
            $table->renameColumn('value_of_residential_property_sales', 'total_value');
        });

        Schema::table('scottish_property_prices', function (Blueprint $table): void {
            $table->decimal('median', 14, 2)->nullable()->change();
            $table->decimal('lower_quartile', 14, 2)->nullable();
            $table->decimal('upper_quartile', 14, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scottish_property_prices', function (Blueprint $table): void {
            $table->dropColumn(['lower_quartile', 'upper_quartile']);
            $table->renameColumn('median', 'median_residential_property_price');
            $table->renameColumn('volume', 'volume_of_residential_property_sales');
            $table->renameColumn('total_value', 'value_of_residential_property_sales');
        });
    }
};
