<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareCacheService
{
    /**
     * Max URLs per purge_cache request. Cloudflare's lowest plan limit is 30;
     * staying at that keeps us safe regardless of plan changes.
     */
    public const URLS_PER_REQUEST = 30;

    protected string $baseUrl;

    protected ?string $apiToken;

    protected ?string $zoneId;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.cloudflare.base_url', 'https://api.cloudflare.com/client/v4'), '/');
        $this->apiToken = config('services.cloudflare.api_token');
        $this->zoneId = config('services.cloudflare.zone_id');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiToken) && ! empty($this->zoneId);
    }

    /**
     * Purge specific URLs from the Cloudflare cache, chunked per request limit.
     *
     * @param  array<int, string>  $urls
     */
    public function purgeUrls(array $urls): void
    {
        foreach (array_chunk(array_values($urls), self::URLS_PER_REQUEST) as $chunk) {
            $this->purge(['files' => $chunk]);
        }
    }

    /**
     * Purge everything cached for the zone.
     */
    public function purgeEverything(): void
    {
        $this->purge(['purge_everything' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function purge(array $payload): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Cloudflare is not configured. Set CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_ID in .env.');
        }

        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->timeout(30)
            ->connectTimeout(15)
            ->post($this->baseUrl.'/zones/'.$this->zoneId.'/purge_cache', $payload);

        if (! $response->successful() || $response->json('success') !== true) {
            Log::warning('Cloudflare cache purge failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $message = collect($response->json('errors', []))->pluck('message')->filter()->implode('; ');

            throw new \RuntimeException('Cloudflare purge failed (HTTP '.$response->status().')'.($message ? ': '.$message : '.'));
        }
    }
}
