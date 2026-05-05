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
        Schema::create('hsi_pages', function (Blueprint $table) {
            $table->id();

            $table->string('seed_url')->nullable();
            $table->string('fetched_url')->nullable();
            $table->string('canonical_url')->nullable();

            // Prefer canonical URL; fall back to normalized fetched URL.
            $table->string('dedupe_key')->unique();

            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('h1s')->nullable();
            $table->json('h2s')->nullable();

            $table->longText('body_text')->nullable();
            $table->longText('raw_html')->nullable();

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('content_type')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hsi_pages');
    }
};

