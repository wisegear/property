<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crime', function (Blueprint $table) {
            $table->id();
            $table->string('crime_id')->nullable();
            $table->date('month');
            $table->string('reported_by')->nullable();
            $table->string('falls_within')->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->string('location')->nullable();
            $table->string('lsoa_code', 20)->nullable();
            $table->string('lsoa_name')->nullable();
            $table->string('crime_type')->nullable();
            $table->string('last_outcome_category')->nullable();
            $table->text('context')->nullable();

            $table->index('month');
            $table->index(['latitude', 'longitude']);
            $table->index('crime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime');
    }
};
