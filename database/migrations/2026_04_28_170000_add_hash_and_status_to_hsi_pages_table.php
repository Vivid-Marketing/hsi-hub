<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hsi_pages', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('dedupe_key');
            $table->string('crawl_status', 32)->nullable()->after('content_type');
            $table->text('last_error')->nullable()->after('crawl_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hsi_pages', function (Blueprint $table) {
            $table->dropColumn(['content_hash', 'crawl_status', 'last_error']);
        });
    }
};

