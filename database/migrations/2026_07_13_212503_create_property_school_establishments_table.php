<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        }

        Schema::create('property_school_establishments', function (Blueprint $table) {
            $table->id();
            $table->string('urn')->nullable();
            $table->string('la_code')->nullable();
            $table->string('la_name')->nullable();
            $table->string('establishment_number')->nullable();
            $table->string('establishment_name')->nullable();
            $table->string('type_of_establishment_code')->nullable();
            $table->string('type_of_establishment_name')->nullable();
            $table->string('establishment_type_group_code')->nullable();
            $table->string('establishment_type_group_name')->nullable();
            $table->string('establishment_status_code')->nullable();
            $table->string('establishment_status_name')->nullable();
            $table->string('reason_establishment_opened_code')->nullable();
            $table->string('reason_establishment_opened_name')->nullable();
            $table->date('open_date')->nullable();
            $table->string('reason_establishment_closed_code')->nullable();
            $table->string('reason_establishment_closed_name')->nullable();
            $table->date('close_date')->nullable();
            $table->string('phase_of_education_code')->nullable();
            $table->string('phase_of_education_name')->nullable();
            $table->integer('statutory_low_age')->nullable();
            $table->integer('statutory_high_age')->nullable();
            $table->string('boarders_code')->nullable();
            $table->string('boarders_name')->nullable();
            $table->string('nursery_provision_name')->nullable();
            $table->string('official_sixth_form_code')->nullable();
            $table->string('official_sixth_form_name')->nullable();
            $table->string('gender_code')->nullable();
            $table->string('gender_name')->nullable();
            $table->string('religious_character_code')->nullable();
            $table->string('religious_character_name')->nullable();
            $table->string('religious_ethos_name')->nullable();
            $table->string('diocese_code')->nullable();
            $table->string('diocese_name')->nullable();
            $table->string('admissions_policy_code')->nullable();
            $table->string('admissions_policy_name')->nullable();
            $table->integer('school_capacity')->nullable();
            $table->string('special_classes_code')->nullable();
            $table->string('special_classes_name')->nullable();
            $table->date('census_date')->nullable();
            $table->integer('number_of_pupils')->nullable();
            $table->integer('number_of_boys')->nullable();
            $table->integer('number_of_girls')->nullable();
            $table->decimal('percentage_fsm', 5, 2)->nullable();
            $table->string('trust_school_flag_code')->nullable();
            $table->string('trust_school_flag_name')->nullable();
            $table->string('trusts_code')->nullable();
            $table->string('trusts_name')->nullable();
            $table->string('school_sponsor_flag_name')->nullable();
            $table->string('school_sponsors_name')->nullable();
            $table->string('federation_flag_name')->nullable();
            $table->string('federations_code')->nullable();
            $table->string('federations_name')->nullable();
            $table->string('ukprn')->nullable();
            $table->string('fehe_identifier')->nullable();
            $table->string('further_education_type_name')->nullable();
            $table->date('last_changed_date')->nullable();
            $table->string('street')->nullable();
            $table->string('locality')->nullable();
            $table->string('address3')->nullable();
            $table->string('town')->nullable();
            $table->string('county_name')->nullable();
            $table->string('postcode')->nullable();
            $table->string('school_website')->nullable();
            $table->string('telephone_num')->nullable();
            $table->string('head_title_name')->nullable();
            $table->string('head_first_name')->nullable();
            $table->string('head_last_name')->nullable();
            $table->string('head_preferred_job_title')->nullable();
            $table->string('bso_inspectorate_name')->nullable();
            $table->string('inspectorate_report')->nullable();
            $table->date('date_of_last_inspection_visit')->nullable();
            $table->date('next_inspection_visit')->nullable();
            $table->string('teen_moth_name')->nullable();
            $table->integer('teen_moth_places')->nullable();
            $table->string('ccf_name')->nullable();
            $table->string('senpru_name')->nullable();
            $table->string('ebd_name')->nullable();
            $table->integer('places_pru')->nullable();
            $table->string('ft_prov_name')->nullable();
            $table->string('ed_by_other_name')->nullable();
            $table->string('section41_approved_name')->nullable();
            $table->string('sen1_name')->nullable();
            $table->string('sen2_name')->nullable();
            $table->string('sen3_name')->nullable();
            $table->string('sen4_name')->nullable();
            $table->string('sen5_name')->nullable();
            $table->string('sen6_name')->nullable();
            $table->string('sen7_name')->nullable();
            $table->string('sen8_name')->nullable();
            $table->string('sen9_name')->nullable();
            $table->string('sen10_name')->nullable();
            $table->string('sen11_name')->nullable();
            $table->string('sen12_name')->nullable();
            $table->string('sen13_name')->nullable();
            $table->string('type_of_resourced_provision_name')->nullable();
            $table->integer('resourced_provision_on_roll')->nullable();
            $table->integer('resourced_provision_capacity')->nullable();
            $table->integer('sen_unit_on_roll')->nullable();
            $table->integer('sen_unit_capacity')->nullable();
            $table->string('gor_code')->nullable();
            $table->string('gor_name')->nullable();
            $table->string('district_administrative_code')->nullable();
            $table->string('district_administrative_name')->nullable();
            $table->string('administrative_ward_code')->nullable();
            $table->string('administrative_ward_name')->nullable();
            $table->string('parliamentary_constituency_code')->nullable();
            $table->string('parliamentary_constituency_name')->nullable();
            $table->string('urban_rural_code')->nullable();
            $table->string('urban_rural_name')->nullable();
            $table->string('gssla_code')->nullable();
            $table->integer('easting')->nullable();
            $table->integer('northing')->nullable();
            $table->string('msoa_name')->nullable();
            $table->string('lsoa_name')->nullable();
            $table->string('inspectorate_name')->nullable();
            $table->integer('sen_stat')->nullable();
            $table->integer('sen_no_stat')->nullable();
            $table->string('boarding_establishment_name')->nullable();
            $table->string('props_name')->nullable();
            $table->string('previous_la_code')->nullable();
            $table->string('previous_la_name')->nullable();
            $table->string('previous_establishment_number')->nullable();
            $table->string('country_name')->nullable();
            $table->string('uprn')->nullable();
            $table->string('site_name')->nullable();
            $table->string('qab_name_code')->nullable();
            $table->string('qab_name')->nullable();
            $table->string('establishment_accredited_code')->nullable();
            $table->string('establishment_accredited_name')->nullable();
            $table->string('qab_report')->nullable();
            $table->string('ch_number')->nullable();
            $table->string('msoa_code')->nullable();
            $table->string('lsoa_code')->nullable();
            $table->integer('fsm')->nullable();
            $table->date('accreditation_expiry_date')->nullable();
            $table->geometry('location', subtype: 'point', srid: 4326)
                ->nullable()
                ->comment('Importer should convert source British National Grid EPSG:27700 easting/northing to WGS84 EPSG:4326, for example with ST_Transform.');
            $table->timestamps();

            $table->unique('urn', 'property_school_establishments_urn_unique');
            $table->index('postcode', 'property_school_establishments_postcode_index');
            $table->index('establishment_status_code', 'property_school_establishments_status_code_index');
            $table->index('phase_of_education_code', 'property_school_establishments_phase_code_index');
            $table->index('type_of_establishment_code', 'property_school_establishments_type_code_index');
            $table->index('la_code', 'property_school_establishments_la_code_index');
            $table->index('district_administrative_code', 'property_school_establishments_district_code_index');
            $table->index('administrative_ward_code', 'property_school_establishments_ward_code_index');
            $table->index('lsoa_code', 'property_school_establishments_lsoa_code_index');
            $table->index('msoa_code', 'property_school_establishments_msoa_code_index');
            $table->index('uprn', 'property_school_establishments_uprn_index');
            $table->index(['establishment_status_code', 'phase_of_education_code'], 'property_school_establishments_status_phase_index');
            $table->index(['la_code', 'phase_of_education_code'], 'property_school_establishments_la_phase_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX property_school_establishments_location_gist_index ON property_school_establishments USING gist (location)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS property_school_establishments_location_gist_index');
        }

        Schema::dropIfExists('property_school_establishments');
    }
};
