<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HsiPage extends Model
{
    protected $table = 'hsi_pages';

    protected $fillable = [
        'seed_url',
        'fetched_url',
        'canonical_url',
        'dedupe_key',
        'content_hash',
        'title',
        'meta_description',
        'h1s',
        'h2s',
        'body_text',
        'raw_html',
        'http_status',
        'content_type',
        'crawl_status',
        'last_error',
        'error',
        'last_crawled_at',
    ];

    protected $casts = [
        'h1s' => 'array',
        'h2s' => 'array',
        'last_crawled_at' => 'datetime',
    ];
}

