<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_api_data_singles', function (Blueprint $table) {
            $table->text('vimeoId')->nullable()->after('cldId');
        });
    }

    public function down(): void
    {
        Schema::table('course_api_data_singles', function (Blueprint $table) {
            $table->dropColumn('vimeoId');
        });
    }
};
