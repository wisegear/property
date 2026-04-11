<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scottish_property_prices', function (Blueprint $table) {
            $table->id();
            $table->string('month');
            $table->string('local_authority');
            $table->string('local_authority_code', 12);
            $table->unsignedInteger('median_residential_property_price')->nullable();
            $table->unsignedInteger('mean_residential_property_price')->nullable();
            $table->unsignedInteger('volume_of_residential_property_sales')->nullable();
            $table->unsignedBigInteger('value_of_residential_property_sales')->nullable();
            $table->timestamps();

            $table->index('local_authority');
            $table->index('local_authority_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scottish_property_prices');
    }
};
