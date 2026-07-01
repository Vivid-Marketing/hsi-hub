<?php

return [
  'allowed_origins' => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('HSI_AI_ALLOWED_ORIGINS', 'https://craft4.hsi.test,https://stage.hsi.com,https://hsi.com'))
  ))),

  'ollama' => [
    'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
    'chat_model' => env('OLLAMA_CHAT_MODEL', 'llama3.2'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
  ],

  'chunking' => [
    'max_chars' => (int) env('HSI_CHUNK_MAX_CHARS', 800),
    'overlap_chars' => (int) env('HSI_CHUNK_OVERLAP_CHARS', 150),
  ],

  'retrieval' => [
    'page_chunk_limit' => (int) env('HSI_ASK_PAGE_CHUNK_LIMIT', 5),
    'algolia_hit_limit' => (int) env('HSI_ASK_ALGOLIA_HIT_LIMIT', 3),
  ],

  'algolia' => [
    'app_id' => env('ALGOLIA_APP_ID'),
    'search_api_key' => env('ALGOLIA_SEARCH_API_KEY'),
    // Used only by hsi:embed-algolia (Browse API). Falls back to search_api_key if unset.
    'browse_api_key' => env('ALGOLIA_BROWSE_API_KEY'),
    'indexes' => [
      'courses' => env('ALGOLIA_INDEX_COURSES'),
      // Blog and news share one Algolia index by design.
      'blog_news' => env('ALGOLIA_INDEX_BLOG_NEWS'),
    ],
  ],
];
