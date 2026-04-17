<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_api_append_data', function (Blueprint $table) {
            $table->increments('capdid');
            $table->unsignedInteger('craft_id')->nullable();
            $table->text('slug')->nullable();
            $table->text('title')->nullable();
            $table->text('cldId')->nullable();
            $table->text('status')->nullable();
            $table->text('recommended')->nullable();
            $table->text('pricingTier')->nullable();
            $table->text('languages')->nullable();
            $table->text('lessonAffiliation')->nullable();
            $table->dateTime('last_updated')->nullable();
        });

        Schema::create('course_api_to_delete', function (Blueprint $table) {
            $table->increments('ctdlid');
            $table->text('craftid')->nullable();
            $table->text('title')->nullable();
            $table->text('cldid')->nullable();
        });

        Schema::create('course_api_to_delete_backup', function (Blueprint $table) {
            $table->increments('ctdlid');
            $table->text('craftid')->nullable();
            $table->text('title')->nullable();
            $table->text('cldid')->nullable();
            $table->dateTime('date_backed_up')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_api_to_delete_backup');
        Schema::dropIfExists('course_api_to_delete');
        Schema::dropIfExists('course_api_append_data');
    }
};
