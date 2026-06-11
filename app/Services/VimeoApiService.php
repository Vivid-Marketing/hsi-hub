<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VimeoApiService
{
    protected string $baseUrl;

    protected ?string $accessToken;

    protected int $maxVideos;

    /**
     * Allowed "time ago" windows mapped to a human label.
     *
     * @var array<string, string>
     */
    public const PERIODS = [
        'day' => '1 day',
        'week' => '1 week',
        'month' => '1 month',
    ];

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.vimeo.base_url', 'https://api.vimeo.com'), '/');
        $this->accessToken = config('services.vimeo.access_token');
        $this->maxVideos = (int) config('services.vimeo.max_videos', 500);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->accessToken);
    }

    /**
     * Fetch the most recently uploaded videos within the given period.
     *
     * @param  string  $period  One of self::PERIODS keys (day|week|month).
     * @return array<int, array{id: string, title: string, created_time: string}>
     */
    public function recentVideos(string $period = 'day'): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Vimeo access token is not configured. Set VIMEO_ACCESS_TOKEN in .env.');
        }

        $cutoff = $this->cutoffFor($period);

        $videos = [];
        $url = $this->baseUrl.'/me/videos';
        $query = [
            'fields' => 'uri,name,created_time',
            'sort' => 'date',
            'direction' => 'desc',
            'per_page' => 100,
        ];

        while ($url !== null && count($videos) < $this->maxVideos) {
            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Accept' => 'application/vnd.vimeo.*+json;version=3.4',
                ])
                ->timeout(30)
                ->connectTimeout(15)
                ->get($url, $query);

            if (! $response->successful()) {
                Log::warning('Vimeo API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('Vimeo API request failed (HTTP '.$response->status().').');
            }

            $data = $response->json();
            $reachedCutoff = false;

            foreach ($data['data'] ?? [] as $item) {
                $createdTime = $item['created_time'] ?? null;

                if ($createdTime !== null && CarbonImmutable::parse($createdTime)->lessThan($cutoff)) {
                    // Results are sorted newest-first, so once we cross the cutoff we can stop.
                    $reachedCutoff = true;
                    break;
                }

                $videos[] = [
                    'id' => $this->videoIdFromUri((string) ($item['uri'] ?? '')),
                    'title' => (string) ($item['name'] ?? ''),
                    'created_time' => (string) ($createdTime ?? ''),
                ];
            }

            if ($reachedCutoff) {
                break;
            }

            // Vimeo returns a relative paging.next path (or null when done).
            $next = $data['paging']['next'] ?? null;
            $url = $next ? $this->baseUrl.$next : null;
            // The next URL already contains the query string.
            $query = [];
        }

        return $videos;
    }

    protected function cutoffFor(string $period): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'week' => $now->subWeek(),
            'month' => $now->subMonth(),
            default => $now->subDay(),
        };
    }

    protected function videoIdFromUri(string $uri): string
    {
        // URIs look like "/videos/123456789".
        if (preg_match('/(\d+)/', $uri, $matches)) {
            return $matches[1];
        }

        return $uri;
    }
}
