<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('course_api_data_staging');
        Schema::dropIfExists('course_api_data_backup_staging');
    }

    public function down(): void
    {
        // Staging tables removed; use prod tables only (local/dev vs production deployment).
    }
};
