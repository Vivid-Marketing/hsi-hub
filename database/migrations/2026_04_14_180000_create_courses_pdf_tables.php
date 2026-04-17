<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses_pdfs_batches', function (Blueprint $table) {
            $table->increments('batch_id');
            $table->string('job_id', 128);
            $table->unsignedInteger('batch_index');
            $table->unsignedInteger('total_batches');
            $table->string('email', 255);
            $table->longText('serialized_data');
            $table->dateTime('date_entered')->useCurrent();
            $table->dateTime('processed_at')->nullable();
            $table->unsignedInteger('stitched_cpdid')->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();

            $table->unique(['job_id', 'batch_index'], 'uniq_job_batch');
            $table->index('job_id', 'idx_job_id');
            $table->index('status', 'idx_status');
            $table->index('email', 'idx_email');
        });

        Schema::create('courses_pdfs_data', function (Blueprint $table) {
            // Keep legacy shape (cpdid) for easy backfills.
            $table->unsignedInteger('cpdid')->autoIncrement();
            $table->dateTime('date_entered')->nullable();
            $table->longText('serialized_data')->nullable();
            $table->text('email')->nullable();
            $table->text('status')->nullable();

            // Extra fields for cleaner internal logging (optional).
            $table->text('pdf_url')->nullable();
            $table->dateTime('pdf_generated_at')->nullable();
            $table->dateTime('email_sent_at')->nullable();
            $table->string('email_message_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses_pdfs_batches');
        Schema::dropIfExists('courses_pdfs_data');
    }
};

