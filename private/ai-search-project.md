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



Phase 4

1. Your internal cache (Laravel)

Static pages (About, Resources, etc.)
Clean, structured, controllable

2. Algolia indexes

Courses (dynamic, high intent)
Blog/News (content-heavy, SEO-driven)

Arhcitecture

Client (UI / AI search)
        ↓
/api/hsi/search?q=
        ↓
Search Aggregator (Laravel)
   ↙           ↘
HsiPages     Algolia
(DB)         (Courses + Blog)
        ↓
Merged + Ranked Results
