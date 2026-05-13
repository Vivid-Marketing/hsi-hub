<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys_pdf_log_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_ts_ms');
            $table->unsignedInteger('received_at_unix')->nullable();
            $table->string('source', 128)->nullable();
            $table->string('client_ip', 64)->nullable();
            $table->string('hub_ip', 64)->nullable();
            $table->string('level', 16)->nullable();
            $table->string('event_type', 64);
            $table->string('survey', 32);
            $table->string('page', 512)->nullable();
            $table->string('path', 2048);
            $table->string('visitor_id', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('extras')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['visitor_id']);
            $table->index(['survey', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys_pdf_log_events');
    }
};
