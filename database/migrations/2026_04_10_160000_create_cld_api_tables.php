<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cld_api_tokens', function (Blueprint $table) {
            $table->increments('cldatkid');
            $table->text('token')->nullable();
            $table->dateTime('datetime_created')->nullable();
        });

        Schema::create('course_api_data', function (Blueprint $table) {
            $table->increments('capdid');
            $table->text('title')->nullable();
            $table->text('cldId')->nullable();
            $table->text('salesLibraryTopic')->nullable();
            $table->text('courseTopic')->nullable();
            $table->text('collections')->nullable();
            $table->text('vendorId')->nullable();
            $table->text('vendorName')->nullable();
            $table->text('libraryId')->nullable();
            $table->text('libraryName')->nullable();
            $table->text('lessonId')->nullable();
            $table->text('ej4CourseNumber')->nullable();
            $table->text('lessonModality')->nullable();
            $table->text('hsiProgramID')->nullable();
            $table->text('lessonLength')->nullable();
            $table->text('lessonAffiliations')->nullable();
            $table->text('locale')->nullable();
            $table->text('allLocales')->nullable();
            $table->text('courseLanguageCategoriesSlug')->nullable();
            $table->text('pricingTier')->nullable();
            $table->text('cldImageUrl')->nullable();
            $table->text('courseImageUrl')->nullable();
            $table->text('courseImageThumbUrl')->nullable();
            $table->text('courseInformation')->nullable();
            $table->text('marketingDescription')->nullable();
            $table->text('courseOutline')->nullable();
            $table->text('courseObjectives')->nullable();
            $table->text('courseRegulations')->nullable();
            $table->text('parentCldid')->nullable();
            $table->string('isRecommended', 255)->nullable();
            $table->dateTime('dateAdded')->nullable();
        });

        Schema::create('course_api_data_backup', function (Blueprint $table) {
            $table->increments('capdid');
            $table->text('title')->nullable();
            $table->text('cldId')->nullable();
            $table->text('salesLibraryTopic')->nullable();
            $table->text('courseTopic')->nullable();
            $table->text('collections')->nullable();
            $table->text('vendorId')->nullable();
            $table->text('vendorName')->nullable();
            $table->text('libraryId')->nullable();
            $table->text('libraryName')->nullable();
            $table->text('lessonId')->nullable();
            $table->text('ej4CourseNumber')->nullable();
            $table->text('lessonModality')->nullable();
            $table->text('hsiProgramID')->nullable();
            $table->text('lessonLength')->nullable();
            $table->text('lessonAffiliations')->nullable();
            $table->text('locale')->nullable();
            $table->text('allLocales')->nullable();
            $table->text('courseLanguageCategoriesSlug')->nullable();
            $table->text('pricingTier')->nullable();
            $table->text('cldImageUrl')->nullable();
            $table->text('courseImageUrl')->nullable();
            $table->text('courseImageThumbUrl')->nullable();
            $table->text('courseInformation')->nullable();
            $table->text('marketingDescription')->nullable();
            $table->text('courseOutline')->nullable();
            $table->text('courseObjectives')->nullable();
            $table->text('courseRegulations')->nullable();
            $table->text('parentCldid')->nullable();
            $table->dateTime('date_backed_up')->nullable();
            $table->string('isRecommended', 255)->nullable();
        });

        Schema::create('course_api_data_singles', function (Blueprint $table) {
            $table->increments('capdid');
            $table->text('title')->nullable();
            $table->text('cldId')->nullable();
            $table->text('salesLibraryTopic')->nullable();
            $table->text('courseTopic')->nullable();
            $table->text('collections')->nullable();
            $table->text('vendorId')->nullable();
            $table->text('vendorName')->nullable();
            $table->text('libraryId')->nullable();
            $table->text('libraryName')->nullable();
            $table->text('lessonId')->nullable();
            $table->text('ej4CourseNumber')->nullable();
            $table->text('lessonModality')->nullable();
            $table->text('hsiProgramID')->nullable();
            $table->text('lessonLength')->nullable();
            $table->text('lessonAffiliations')->nullable();
            $table->text('locale')->nullable();
            $table->text('allLocales')->nullable();
            $table->text('courseLanguageCategoriesSlug')->nullable();
            $table->text('pricingTier')->nullable();
            $table->text('cldImageUrl')->nullable();
            $table->text('courseImageUrl')->nullable();
            $table->text('courseImageThumbUrl')->nullable();
            $table->text('courseInformation')->nullable();
            $table->text('marketingDescription')->nullable();
            $table->text('courseOutline')->nullable();
            $table->text('courseObjectives')->nullable();
            $table->text('courseRegulations')->nullable();
            $table->text('parentCldid')->nullable();
            $table->string('isRecommended', 255)->nullable();
            $table->dateTime('dateAdded')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_api_data_singles');
        Schema::dropIfExists('course_api_data_backup');
        Schema::dropIfExists('course_api_data');
        Schema::dropIfExists('cld_api_tokens');
    }
};
