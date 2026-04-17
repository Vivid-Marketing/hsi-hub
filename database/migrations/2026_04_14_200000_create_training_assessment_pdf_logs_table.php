<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_assessment_pdf_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // default|hrca|qew
            $table->string('origin')->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('title', 200);
            $table->string('name', 200);
            $table->string('status', 32); // success|failed
            $table->text('pdf_url')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('request_payload')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_assessment_pdf_logs');
    }
};

