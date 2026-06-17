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
            $table->string('source_group', 32)->nullable()->after('content_hash');
            $table->string('page_type', 32)->nullable()->after('source_group');
            $table->text('ai_summary')->nullable()->after('h2s');
            $table->json('search_keywords')->nullable()->after('ai_summary');
            $table->json('primary_topics')->nullable()->after('search_keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hsi_pages', function (Blueprint $table) {
            $table->dropColumn([
                'source_group',
                'page_type',
                'ai_summary',
                'search_keywords',
                'primary_topics',
            ]);
        });
    }
};
