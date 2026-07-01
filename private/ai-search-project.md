Goal:
Crawl primary HSI pages from main nav + footer links, normalize the content, and cache structured page data for future AI/search use.

Phase 1:
- Manually seed top nav + footer URLs
- Fetch each page
- Extract title, meta description, H1s, H2s, body text, canonical URL
- Store raw HTML + cleaned text + last_crawled_at
- Add a CLI command: php artisan hsi:crawl-pages

Phase 2:
- Add change detection/hash
- Add scheduled recrawling
- Add searchable cache endpoint
- Later: vector embeddings only if needed

Phase 2
1. Add content_hash to hsi_pages
2. On crawl, compare new hash vs existing hash
3. Only update body/raw fields when content changed
4. Add crawl_status, last_error, http_status
5. Add API endpoint:
   GET /api/hsi/pages/search?q=
6. Add scheduled command:
   php artisan hsi:crawl-pages --max=50


Phase 3: AI-ready retrieval layer:

1. Add page_type or source_group
   Example: main_nav, footer, resource, landing_page

2. Add summary fields
   ai_summary
   search_keywords
   primary_topics

3. Add admin/debug endpoint
   GET /api/hsi/pages
   GET /api/hsi/pages/{id}

4. Add search ranking
   Title match > H1/H2 match > body_text match

5. Add endpoint for AI/search use
   GET /api/hsi/pages/retrieve?q=
   Returns compact context blocks, not raw full pages


Phase 4: Unified keyword search (Keyword tab)

1. Internal cache (Laravel) for static pages
2. Algolia indexes for Courses, Blog, News
3. GET /api/hsi/search?q= merges both sources


Phase 5: Semantic Q&A (default search modal tab)

Architecture (hybrid — do NOT re-embed Algolia for MVP):

Client search modal
  ├── Tab 1 (default): AI Q&A → POST /api/hsi/ask { q }
  └── Tab 2: Keyword search → GET /api/hsi/search?q=

Ask flow:
1. Embed user question (Ollama: nomic-embed-text)
2. Vector search local page chunks (hsi_chunks table)
3. Algolia search at query time (courses, blog, news) — use hits as context, no duplicate embedding pipeline
4. Merge context blocks
5. Ollama chat model generates grounded answer + source links

Static pages:
- php artisan hsi:crawl-pages
- php artisan hsi:embed-pages

Tables:
- hsi_pages (crawl cache)
- hsi_chunks (chunk text + embedding JSON)

Config:
- config/hsi_ai.php (Ollama + Algolia + chunk settings)

Endpoints:
- POST /api/hsi/ask?q= or { "q": "..." }
- GET /api/hsi/search?q= (keyword tab)

Env:
- OLLAMA_BASE_URL, OLLAMA_EMBED_MODEL, OLLAMA_CHAT_MODEL
- ALGOLIA_APP_ID, ALGOLIA_SEARCH_API_KEY
- ALGOLIA_INDEX_COURSES, ALGOLIA_INDEX_BLOG, ALGOLIA_INDEX_NEWS

Frontend (hsi.com):
- Modal defaults to AI tab
- Show answer + source link cards (pages + courses + blog/news)
- Keyword tab calls /api/hsi/search

Later (optional):
- Stream answers from Ollama
- Conversation history
- Re-embed Algolia into vectors only if query-time Algolia context is insufficient
- pgvector / dedicated vector DB if chunk count grows large
- Production LLM (OpenAI/Anthropic) instead of Ollama


Architecture

Client (UI / AI search)
        ↓
POST /api/hsi/ask  |  GET /api/hsi/search
        ↓
Laravel Hub
   ↙         ↓           ↘
Vector      Algolia      Keyword
(chunks)    (query-time) (pages DB)
        ↓
     Ollama LLM
        ↓
Answer + source links
