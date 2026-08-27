<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['course_api_data', 'course_api_data_singles', 'course_api_data_backup'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'courseOutlineList')) {
                    $blueprint->text('courseOutlineList')->nullable()->after('courseOutline');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['course_api_data', 'course_api_data_singles', 'course_api_data_backup'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'courseOutlineList')) {
                    $blueprint->dropColumn('courseOutlineList');
                }
            });
        }
    }
};
