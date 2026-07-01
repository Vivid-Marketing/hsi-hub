<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HsiChunk extends Model
{
  protected $table = 'hsi_chunks';

  protected $fillable = [
    'hsi_page_id',
    'source_type',
    'source_id',
    'source_url',
    'source_title',
    'chunk_index',
    'content',
    'content_hash',
    'embedding_model',
    'embedding',
    'embedded_at',
  ];

  protected $casts = [
    'embedding' => 'array',
    'embedded_at' => 'datetime',
  ];

  public function page(): BelongsTo
  {
    return $this->belongsTo(HsiPage::class, 'hsi_page_id');
  }
}
