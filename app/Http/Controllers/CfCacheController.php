<?php

namespace App\Http\Controllers;

use App\Services\CloudflareCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CfCacheController extends Controller
{
    protected CloudflareCacheService $cloudflare;

    public function __construct(CloudflareCacheService $cloudflare)
    {
        $this->cloudflare = $cloudflare;
    }

    /**
     * Show the Cloudflare cache purge page.
     */
    public function index(): View
    {
        return view('cf-cache.index', [
            'configured' => $this->cloudflare->isConfigured(),
        ]);
    }

    /**
     * Purge the URLs entered in the textarea (one per line).
     */
    public function purgeUrls(Request $request): RedirectResponse
    {
        $request->validate([
            'urls' => 'required|string',
        ]);

        $lines = collect(preg_split('/\r\n|\r|\n/', $request->input('urls')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->values();

        $invalid = $lines->reject(fn ($url) => $this->isHsiUrl($url))->values();

        if ($invalid->isNotEmpty()) {
            return back()->withInput()->with('error', 'These lines are not valid hsi.com URLs (include https://): '.$invalid->implode(', '));
        }

        try {
            $this->cloudflare->purgeUrls($lines->all());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        Log::info('Cloudflare cache purged by URL', [
            'user_id' => $request->user()->id,
            'urls' => $lines->all(),
        ]);

        return back()->with('success', 'Cleared '.$lines->count().' URL(s) from the Cloudflare cache.');
    }

    /**
     * Purge everything cached for the zone.
     */
    public function purgeAll(Request $request): RedirectResponse
    {
        try {
            $this->cloudflare->purgeEverything();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        Log::warning('Cloudflare cache purged (everything)', [
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'All Cloudflare cache for hsi.com has been cleared.');
    }

    /**
     * Only absolute http(s) URLs on hsi.com (or a subdomain) can be purged by this zone.
     */
    protected function isHsiUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($scheme, ['http', 'https'], true)
            && ($host === 'hsi.com' || str_ends_with($host, '.hsi.com'));
    }
}
