<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_schools', function (Blueprint $table) {
            $table->id();
            $table->string('web_link_opens_in_new_window')->nullable();
            $table->integer('urn')->nullable();
            $table->integer('laestab')->nullable();
            $table->string('school_name')->nullable();
            $table->string('ofsted_phase')->nullable();
            $table->string('type_of_education')->nullable();
            $table->date('school_open_date')->nullable();
            $table->string('admissions_policy')->nullable();
            $table->string('sixth_form')->nullable();
            $table->string('designated_religious_character')->nullable();
            $table->string('religious_ethos')->nullable();
            $table->string('faith_grouping')->nullable();
            $table->string('ofsted_region')->nullable();
            $table->string('region')->nullable();
            $table->string('local_authority')->nullable();
            $table->string('parliamentary_constituency')->nullable();
            $table->string('postcode')->nullable();
            $table->integer('multi_academy_trust_uid')->nullable();
            $table->string('multi_academy_trust_name')->nullable();
            $table->integer('academy_sponsor_uid')->nullable();
            $table->string('academy_sponsor_name')->nullable();
            $table->integer('the_income_deprivation_affecting_children_index_idaci_quintile')->nullable();
            $table->integer('total_number_of_pupils')->nullable();
            $table->integer('statutory_lowest_age')->nullable();
            $table->integer('statutory_highest_age')->nullable();
            $table->integer('inspection_number_of_latest_full_inspection')->nullable();
            $table->string('inspection_type')->nullable();
            $table->string('inspection_type_grouping')->nullable();
            $table->string('event_type_grouping')->nullable();
            $table->date('inspection_start_date')->nullable();
            $table->date('publication_date')->nullable();
            $table->boolean('latest_full_inspection_relates_to_current_urn')->nullable();
            $table->integer('urn_at_time_of_latest_full_inspection')->nullable();
            $table->integer('laestab_at_time_of_latest_full_inspection')->nullable();
            $table->string('school_name_at_time_of_latest_full_inspection')->nullable();
            $table->string('school_type_at_time_of_latest_full_inspection')->nullable();
            $table->string('category_of_concern')->nullable();
            $table->string('safeguarding_standards')->nullable();
            $table->date('safeguarding_standards_date_of_grade')->nullable();
            $table->string('inclusion')->nullable();
            $table->date('inclusion_date_of_grade')->nullable();
            $table->string('curriculum_and_teaching')->nullable();
            $table->date('curriculum_and_teaching_date_of_grade')->nullable();
            $table->string('achievement')->nullable();
            $table->date('achievement_date_of_grade')->nullable();
            $table->string('attendance_and_behaviour')->nullable();
            $table->date('attendance_and_behaviour_date_of_grade')->nullable();
            $table->string('personal_development_and_wellbeing')->nullable();
            $table->date('personal_development_and_wellbeing_date_of_grade')->nullable();
            $table->string('early_years_where_applicable')->nullable();
            $table->date('early_years_date_of_grade')->nullable();
            $table->string('post_16_provision_where_applicable')->nullable();
            $table->date('post_16_provision_date_of_grade')->nullable();
            $table->string('leadership_and_governance')->nullable();
            $table->date('leadership_and_governance_date_of_grade')->nullable();
            $table->integer('inspection_number_of_latest_oeif_graded_inspection')->nullable();
            $table->string('inspection_type_of_latest_oeif_graded_inspection')->nullable();
            $table->string('inspection_type_grouping_of_latest_oeif_graded_inspection')->nullable();
            $table->string('event_type_grouping_of_latest_oeif_graded_inspection')->nullable();
            $table->date('inspection_start_date_of_latest_oeif_graded_inspection')->nullable();
            $table->date('publication_date_of_latest_oeif_graded_inspection')->nullable();
            $table->boolean('latest_oeif_graded_inspection_relates_to_current_urn')->nullable();
            $table->integer('urn_at_time_of_latest_oeif_graded_inspection')->nullable();
            $table->integer('laestab_at_time_of_latest_oeif_graded_inspection')->nullable();
            $table->string('school_name_at_time_of_latest_oeif_graded_inspection')->nullable();
            $table->string('school_type_at_time_of_latest_oeif_graded_inspection')->nullable();
            $table->string('latest_oeif_category_of_concern')->nullable();
            $table->string('latest_oeif_overall_effectiveness')->nullable();
            $table->integer('latest_oeif_quality_of_education')->nullable();
            $table->integer('latest_oeif_behaviour_and_attitudes')->nullable();
            $table->integer('latest_oeif_personal_development')->nullable();
            $table->integer('latest_oeif_effectiveness_of_leadership_and_management')->nullable();
            $table->boolean('latest_oeif_safeguarding_is_effective')->nullable();
            $table->integer('latest_oeif_early_years_provision_where_applicable')->nullable();
            $table->integer('latest_oeif_sixth_form_provision_where_applicable')->nullable();
            $table->integer('latest_ungraded_inspection_number')->nullable();
            $table->date('date_of_latest_ungraded_inspection')->nullable();
            $table->date('ungraded_inspection_publication_date')->nullable();
            $table->boolean('ungraded_inspection_relates_to_current_urn')->nullable();
            $table->integer('urn_at_time_of_the_ungraded_inspection')->nullable();
            $table->integer('laestab_at_time_of_the_ungraded_inspection')->nullable();
            $table->string('school_name_at_time_of_the_ungraded_inspection')->nullable();
            $table->string('school_type_at_time_of_the_ungraded_inspection')->nullable();
            $table->string('ungraded_inspection_overall_outcome')->nullable();
            $table->string('most_recent_category_of_concern')->nullable();
            $table->timestamps();

            $table->unique('urn', 'property_schools_urn_unique');
            $table->index('postcode', 'property_schools_postcode_index');
            $table->index('ofsted_phase', 'property_schools_ofsted_phase_index');
            $table->index('type_of_education', 'property_schools_type_of_education_index');
            $table->index('latest_oeif_overall_effectiveness', 'property_schools_oeif_rating_index');
            $table->index('inspection_start_date_of_latest_oeif_graded_inspection', 'property_schools_oeif_inspection_date_index');
            $table->index('local_authority', 'property_schools_local_authority_index');
            $table->index(['ofsted_phase', 'latest_oeif_overall_effectiveness'], 'property_schools_phase_oeif_rating_index');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX property_schools_school_name_trgm_index ON property_schools USING gin (school_name gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS property_schools_school_name_trgm_index');
        }

        Schema::dropIfExists('property_schools');
    }
};
