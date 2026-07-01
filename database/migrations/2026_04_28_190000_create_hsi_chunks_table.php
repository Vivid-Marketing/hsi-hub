<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('hsi_chunks', function (Blueprint $table) {
      $table->id();
      $table->foreignId('hsi_page_id')->nullable()->constrained('hsi_pages')->cascadeOnDelete();

      $table->string('source_type', 32); // page
      $table->string('source_id')->nullable();
      $table->string('source_url')->nullable();
      $table->string('source_title')->nullable();

      $table->unsignedSmallInteger('chunk_index')->default(0);
      $table->longText('content');
      $table->string('content_hash', 64);

      $table->string('embedding_model')->nullable();
      $table->json('embedding')->nullable();
      $table->timestamp('embedded_at')->nullable();

      $table->timestamps();

      $table->index(['hsi_page_id', 'chunk_index']);
      $table->index('source_type');
      $table->unique(['hsi_page_id', 'chunk_index'], 'hsi_chunks_page_chunk_unique');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('hsi_chunks');
  }
};
