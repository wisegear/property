<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE prime_postcodes DROP CONSTRAINT IF EXISTS prime_postcodes_category_check');
            DB::statement("ALTER TABLE prime_postcodes ADD CONSTRAINT prime_postcodes_category_check CHECK (category IN ('Prime Central', 'Ultra Prime', 'Outer Prime London'))");

            return;
        }

        Schema::table('prime_postcodes', function (Blueprint $table): void {
            $table->enum('category', ['Prime Central', 'Ultra Prime', 'Outer Prime London'])->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::table('prime_postcodes')
                ->where('category', 'Outer Prime London')
                ->update(['category' => 'Prime Central']);

            DB::statement('ALTER TABLE prime_postcodes DROP CONSTRAINT IF EXISTS prime_postcodes_category_check');
            DB::statement("ALTER TABLE prime_postcodes ADD CONSTRAINT prime_postcodes_category_check CHECK (category IN ('Prime Central', 'Ultra Prime'))");

            return;
        }

        Schema::table('prime_postcodes', function (Blueprint $table): void {
            $table->enum('category', ['Prime Central', 'Ultra Prime'])->change();
        });
    }
};
