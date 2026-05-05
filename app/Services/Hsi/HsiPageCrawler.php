<?php

namespace App\Services\Hsi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Arr;
use PHPHtmlParser\Dom;

class HsiPageCrawler
{
    public function __construct(
        private readonly Client $http = new Client(),
    ) {
    }

    /**
     * @return array{
     *   ok: bool,
     *   seed_url: string,
     *   fetched_url: ?string,
     *   canonical_url: ?string,
     *   dedupe_key: string,
     *   content_hash: ?string,
     *   http_status: ?int,
     *   content_type: ?string,
     *   title: ?string,
     *   meta_description: ?string,
     *   h1s: array<int,string>,
     *   h2s: array<int,string>,
     *   body_text: ?string,
     *   raw_html: ?string,
     *   error: ?string,
     * }
     */
    public function crawl(string $seedUrl): array
    {
        $baseUrl = rtrim((string) config('hsi_crawl.base_url', 'https://hsi.com'), '/');
        $seedUrlAbs = $this->toAbsoluteUrl($seedUrl, $baseUrl);

        $effectiveUrl = null;
        try {
            $res = $this->http->request('GET', $seedUrlAbs, [
                'timeout' => 30,
                'allow_redirects' => ['track_redirects' => true],
                'on_stats' => function (TransferStats $stats) use (&$effectiveUrl): void {
                    try {
                        $uri = $stats->getEffectiveUri();
                        if ($uri !== null) {
                            $effectiveUrl = (string) $uri;
                        }
                    } catch (\Throwable) {
                    }
                },
                'headers' => [
                    'User-Agent' => 'HSI-Crawler/1.0 (+https://hsi.com)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.8',
                ],
            ]);
        } catch (GuzzleException $e) {
            $dedupeKey = $this->makeDedupeKey(null, $seedUrlAbs);

            return [
                'ok' => false,
                'seed_url' => $seedUrlAbs,
                'fetched_url' => null,
                'canonical_url' => null,
                'dedupe_key' => $dedupeKey,
                'content_hash' => null,
                'http_status' => null,
                'content_type' => null,
                'title' => null,
                'meta_description' => null,
                'h1s' => [],
                'h2s' => [],
                'body_text' => null,
                'raw_html' => null,
                'error' => $e->getMessage(),
            ];
        }

        $status = $res->getStatusCode();
        $contentType = $res->getHeaderLine('Content-Type') ?: null;
        $html = (string) $res->getBody();

        // Best-effort final URL (after redirects)
        $fetchedUrl = $seedUrlAbs;
        if (is_string($effectiveUrl) && $effectiveUrl !== '') {
            $fetchedUrl = $effectiveUrl;
        } else {
            $hist = $res->getHeader('X-Guzzle-Redirect-History');
            if (!empty($hist)) {
                $fetchedUrl = (string) Arr::last($hist);
            }
        }

        if ($contentType !== null && !str_contains(strtolower($contentType), 'text/html')) {
            $dedupeKey = $this->makeDedupeKey(null, $fetchedUrl);

            return [
                'ok' => false,
                'seed_url' => $seedUrlAbs,
                'fetched_url' => $fetchedUrl,
                'canonical_url' => null,
                'dedupe_key' => $dedupeKey,
                'content_hash' => null,
                'http_status' => $status,
                'content_type' => $contentType,
                'title' => null,
                'meta_description' => null,
                'h1s' => [],
                'h2s' => [],
                'body_text' => null,
                'raw_html' => $html,
                'error' => 'Non-HTML content-type',
            ];
        }

        $dom = new Dom();
        $dom->loadStr($html);

        $title = $this->extractTitle($dom);
        $metaDescription = $this->extractMetaDescription($dom);
        $canonicalUrl = $this->extractCanonicalUrl($dom, $fetchedUrl, $baseUrl);
        $h1s = $this->extractHeadings($dom, 'h1');
        $h2s = $this->extractHeadings($dom, 'h2');
        $bodyText = $this->extractBodyText($dom);

        $dedupeKey = $this->makeDedupeKey($canonicalUrl, $fetchedUrl);
        $contentHash = $this->makeContentHash([
            'canonical_url' => $canonicalUrl,
            'title' => $title,
            'meta_description' => $metaDescription,
            'h1s' => $h1s,
            'h2s' => $h2s,
            'body_text' => $bodyText,
        ]);

        return [
            'ok' => ($status >= 200 && $status < 400),
            'seed_url' => $seedUrlAbs,
            'fetched_url' => $fetchedUrl,
            'canonical_url' => $canonicalUrl,
            'dedupe_key' => $dedupeKey,
            'content_hash' => $contentHash,
            'http_status' => $status,
            'content_type' => $contentType,
            'title' => $title,
            'meta_description' => $metaDescription,
            'h1s' => $h1s,
            'h2s' => $h2s,
            'body_text' => $bodyText,
            'raw_html' => $html,
            'error' => null,
        ];
    }

    private function extractTitle(Dom $dom): ?string
    {
        try {
            $node = $dom->find('title');
            if (!empty($node) && count($node) > 0) {
                return $this->cleanInlineText((string) $node[0]->text);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function extractMetaDescription(Dom $dom): ?string
    {
        try {
            $node = $dom->find('meta[name=description]');
            if (!empty($node) && count($node) > 0) {
                $content = (string) $node[0]->getAttribute('content');
                $content = $this->cleanInlineText($content);
                return $content !== '' ? $content : null;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function extractCanonicalUrl(Dom $dom, string $fetchedUrl, string $baseUrl): ?string
    {
        try {
            $node = $dom->find('link[rel=canonical]');
            if (!empty($node) && count($node) > 0) {
                $href = (string) $node[0]->getAttribute('href');
                $href = trim($href);
                if ($href === '') {
                    return null;
                }
                return $this->normalizeUrl($this->toAbsoluteUrl($href, $fetchedUrl ?: $baseUrl));
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractHeadings(Dom $dom, string $tag): array
    {
        $out = [];
        try {
            $nodes = $dom->find($tag);
            foreach ($nodes as $n) {
                $t = $this->cleanInlineText((string) $n->text);
                if ($t !== '') {
                    $out[] = $t;
                }
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique($out));
    }

    private function extractBodyText(Dom $dom): ?string
    {
        $root = null;

        try {
            $main = $dom->find('main');
            if (!empty($main) && count($main) > 0) {
                $root = $main[0];
            }
        } catch (\Throwable) {
        }

        if ($root === null) {
            try {
                $body = $dom->find('body');
                if (!empty($body) && count($body) > 0) {
                    $root = $body[0];
                }
            } catch (\Throwable) {
            }
        }

        if ($root === null) {
            return null;
        }

        // Remove common non-content blocks.
        foreach (['script', 'style', 'noscript', 'svg', 'header', 'footer', 'nav', 'aside', 'form'] as $sel) {
            try {
                $nodes = $root->find($sel);
                foreach ($nodes as $n) {
                    try {
                        $n->delete();
                    } catch (\Throwable) {
                    }
                }
            } catch (\Throwable) {
            }
        }

        $text = '';
        try {
            $text = (string) $root->text;
        } catch (\Throwable) {
            return null;
        }

        $text = $this->normalizeWhitespace($text);
        if ($text === '') {
            return null;
        }

        return $text;
    }

    private function makeDedupeKey(?string $canonicalUrl, string $fetchedUrl): string
    {
        if (is_string($canonicalUrl) && $canonicalUrl !== '') {
            return $canonicalUrl;
        }

        return $this->normalizeUrl($fetchedUrl);
    }

    private function toAbsoluteUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '') {
            return $this->normalizeUrl($baseUrl);
        }

        if (preg_match('~^https?://~i', $url)) {
            return $this->normalizeUrl($url);
        }

        // protocol-relative
        if (str_starts_with($url, '//')) {
            return $this->normalizeUrl('https:'.$url);
        }

        $base = new Uri($baseUrl);
        $rel = new Uri($url);
        $abs = UriResolver::resolve($base, $rel);

        return $this->normalizeUrl((string) $abs);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        $u = new Uri($url);
        $u = $u
            ->withFragment('')
            ->withQuery('');

        // normalize trailing slash (keep root "/")
        $path = $u->getPath();
        if ($path !== '' && $path !== '/') {
            $path = rtrim($path, '/');
        }
        $u = $u->withPath($path);

        return (string) $u;
    }

    private function cleanInlineText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        return $this->normalizeWhitespace($text);
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace("\xc2\xa0", ' ', $text); // nbsp
        $text = preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function makeContentHash(array $payload): ?string
    {
        if (empty($payload['body_text']) && empty($payload['title']) && empty($payload['meta_description'])) {
            return null;
        }

        // Ensure stable ordering.
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }

        return hash('sha256', $json);
    }
}

