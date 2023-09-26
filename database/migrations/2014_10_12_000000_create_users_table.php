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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_slug');
            $table->string('email')->unique();
            $table->boolean('email_visible')->default(false);
            $table->string('avatar')->default('default.png');
            $table->String('bio', 1000)->nullable();
            $table->string('website')->nullable();
            $table->string('location')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('facebook')->nullable();
            $table->string('x')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('trusted')->default(false);
            $table->String('notes')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
