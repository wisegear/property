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
        Schema::create('land_registry_inspire', function (Blueprint $table) {
            $table->id();
            $table->char('transaction_id', 38)->index();
            $table->string('inspire_id')->index();

            $table->unique(['transaction_id', 'inspire_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('land_registry_inspire');
    }
};
