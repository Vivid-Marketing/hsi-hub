<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cld_sync_runs', function (Blueprint $table) {
            $table->id();

            // "full" (cld:sync) or "singles" (cld:sync-singles / Courses UI)
            $table->string('mode', 32);

            // "cli:cld:sync", "cli:cld:sync-singles", or "ui:/courses"
            $table->string('trigger', 64);

            // For singles runs, store the requested IDs as a comma-separated string (keeps this table simple/portable).
            $table->text('requested_ids')->nullable();

            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('succeeded')->default(0);
            $table->unsignedInteger('failed')->default(0);

            $table->boolean('send_to_craft')->default(false);
            $table->boolean('feedme_configured')->default(false);
            $table->boolean('feedme_ran')->default(false);
            $table->boolean('feedme_ok')->nullable();
            $table->unsignedInteger('feedme_http_code')->nullable();

            $table->text('abort_reason')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Extra details for debugging (JSON is supported in MySQL/Postgres; falls back to TEXT where needed).
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cld_sync_runs');
    }
};

