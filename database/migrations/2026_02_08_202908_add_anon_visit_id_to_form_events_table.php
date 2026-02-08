<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_events', function (Blueprint $table) {
            $table->string('anon_visit_id', 36)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('form_events', function (Blueprint $table) {
            $table->dropIndex(['anon_visit_id']);
            $table->dropColumn('anon_visit_id');
        });
    }
};
