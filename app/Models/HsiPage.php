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
        'source_group',
        'page_type',
        'title',
        'meta_description',
        'h1s',
        'h2s',
        'ai_summary',
        'search_keywords',
        'primary_topics',
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
        'search_keywords' => 'array',
        'primary_topics' => 'array',
        'last_crawled_at' => 'datetime',
    ];
}

