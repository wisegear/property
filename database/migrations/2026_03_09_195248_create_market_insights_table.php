<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_insights', function (Blueprint $table) {
            $table->id();
            $table->string('area_type');
            $table->string('area_code');
            $table->string('insight_type');
            $table->decimal('metric_value', 10, 2)->nullable();
            $table->integer('transactions')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('supporting_data')->nullable();
            $table->text('insight_text');
            $table->timestamps();

            $table->index(['area_type', 'area_code']);
            $table->index('insight_type');
            $table->index('period_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_insights');
    }
};
