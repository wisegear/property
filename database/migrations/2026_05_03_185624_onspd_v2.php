<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onspd_v2', function (Blueprint $table) {
            $table->id();

            // Postcodes
            $table->string('pcd7', 10)->nullable();
            $table->string('pcd8', 10)->nullable();
            $table->string('pcds', 10)->nullable()->unique();

            // Lifecycle
            $table->string('dointr', 6)->nullable()->index();
            $table->string('doterm', 6)->nullable()->index();

            // Administrative geographies (latest versions)
            $table->string('cty25cd', 12)->nullable()->index();
            $table->string('ced25cd', 12)->nullable()->index();
            $table->string('lad25cd', 12)->nullable()->index();
            $table->string('wd25cd', 12)->nullable()->index();
            $table->string('parncp25cd', 12)->nullable()->index();

            // Classification and grid
            $table->char('usrtypind', 1)->nullable();
            $table->integer('east1m')->nullable();
            $table->integer('north1m')->nullable();
            $table->tinyInteger('gridind')->nullable();

            // Health / NHS
            $table->string('hlth19cd', 12)->nullable()->index();
            $table->string('nhser24cd', 12)->nullable()->index();

            // Country / region / parliamentary etc
            $table->string('ctry25cd', 12)->nullable()->index();
            $table->string('rgn25cd', 12)->nullable()->index();
            $table->string('ssr95cd', 12)->nullable()->index();
            $table->string('pcon24cd', 12)->nullable()->index();
            $table->string('eer20cd', 12)->nullable()->index();
            $table->string('educ23cd', 12)->nullable()->index();
            $table->string('ttwa15cd', 12)->nullable()->index();
            $table->string('pco19cd', 12)->nullable()->index();
            $table->string('itl25cd', 12)->nullable()->index();
            $table->string('wdstl05cd', 12)->nullable()->index();

            // Older statistical geographies
            $table->string('oa01cd', 12)->nullable()->index();
            $table->string('wdcas03cd', 12)->nullable()->index();
            $table->string('npark16cd', 12)->nullable()->index();
            $table->string('lsoa01cd', 12)->nullable()->index();
            $table->string('msoa01cd', 12)->nullable()->index();
            $table->string('ruc01ind', 12)->nullable()->index();
            $table->string('oac01ind', 12)->nullable()->index();

            // 2011 geographies (IMPORTANT for IMD 2019)
            $table->string('oa11cd', 12)->nullable()->index();
            $table->string('lsoa11cd', 12)->nullable()->index();
            $table->string('msoa11cd', 12)->nullable()->index();
            $table->string('wz11cd', 12)->nullable()->index();
            $table->string('sicbl24cd', 12)->nullable()->index();
            $table->string('bua24cd', 12)->nullable()->index();
            $table->string('ruc11ind', 12)->nullable()->index();
            $table->string('oac11ind', 12)->nullable()->index();

            // Coordinates
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('long', 9, 6)->nullable();

            // Partnerships / police / deprivation
            $table->string('lep21cd1', 12)->nullable()->index();
            $table->string('lep21cd2', 12)->nullable()->index();
            $table->string('pfa23cd', 12)->nullable()->index();
            $table->integer('imd20ind')->nullable();

            // Newer health/admin
            $table->string('cal24cd', 12)->nullable()->index();
            $table->string('icb23cd', 12)->nullable()->index();

            // 2021 geographies
            $table->string('oa21cd', 12)->nullable()->index();
            $table->string('lsoa21cd', 12)->nullable()->index();
            $table->string('msoa21cd', 12)->nullable()->index();
            $table->string('ruc21ind', 12)->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onspd_v2');
    }
};
