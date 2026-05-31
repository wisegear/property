<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swap_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date');
            $table->string('curve_type')->default('ois');
            $table->unsignedSmallInteger('term_years');
            $table->decimal('rate', 8, 4);
            $table->decimal('daily_change', 8, 4)->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['rate_date', 'curve_type', 'term_years']);
            $table->index(['term_years', 'rate_date']);
            $table->index('rate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swap_rates');
    }
};
