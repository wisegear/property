<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mortgage_approvals')
            ->whereNull('unit')
            ->update(['unit' => 'count']);

        Schema::table('mortgage_approvals', function (Blueprint $table) {
            $table->string('unit', 16)->nullable()->default('count')->change();
        });
    }

    public function down(): void
    {
        Schema::table('mortgage_approvals', function (Blueprint $table) {
            $table->string('unit', 16)->nullable()->default(null)->change();
        });
    }
};
