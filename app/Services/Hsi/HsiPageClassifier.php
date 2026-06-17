<?php

namespace App\Services\Hsi;

class HsiPageClassifier
{
    public function sourceGroupForSeedPath(string $seedPath): string
    {
        $path = $this->normalizePath($seedPath);
        $map = (array) config('hsi_crawl.source_groups', []);

        if (isset($map[$path])) {
            return (string) $map[$path];
        }

        return (string) config('hsi_crawl.source_group_default', 'main_nav');
    }

    public function pageTypeForUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $path = $this->normalizePath($path);

        if ($path === '/') {
            return 'landing_page';
        }

        if (preg_match('#^/(resources|blog|news|podcast)(/|$)#', $path)) {
            return 'resource';
        }

        if (str_starts_with($path, '/solutions/')) {
            return 'solution';
        }

        if (str_starts_with($path, '/industries/')) {
            return 'industry';
        }

        if (str_starts_with($path, '/services/')) {
            return 'service';
        }

        if (in_array($path, ['/privacy', '/terms', '/legal'], true)) {
            return 'legal';
        }

        if (in_array($path, ['/about-hsi', '/contact', '/partnerships', '/careers'], true)) {
            return 'company';
        }

        if ($path === '/courses' || str_contains($path, '/courses')) {
            return 'course';
        }

        return 'page';
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
