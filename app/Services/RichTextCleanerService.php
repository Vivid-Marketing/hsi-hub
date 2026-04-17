<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class RichTextCleanerService
{
    /**
     * Remove target="_blank" and rel="noopener/noreferrer" from all links
     *
     * @return array{cleaned_html: string, stats: array{links_cleaned: int, targets_removed: int, rels_cleaned: int}}
     */
    public function cleanLinks(string $html): array
    {
        if (empty(trim($html))) {
            return [
                'cleaned_html' => $html,
                'stats' => [
                    'links_cleaned' => 0,
                    'targets_removed' => 0,
                    'rels_cleaned' => 0,
                ],
            ];
        }

        // Create a new DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);

        // Wrap HTML in a container div to handle fragments properly
        // This ensures DOMDocument can parse fragments without adding html/body tags
        $wrappedHtml = '<div id="rich-text-container">'.$html.'</div>';

        // Load HTML with UTF-8 encoding
        $dom->loadHTML('<?xml encoding="UTF-8">'.$wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Clear libxml errors
        libxml_clear_errors();

        // Find all anchor tags
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a');

        $stats = [
            'links_cleaned' => 0,
            'targets_removed' => 0,
            'rels_cleaned' => 0,
        ];

        foreach ($links as $link) {
            $stats['links_cleaned']++;

            // Remove target attribute
            if ($link->hasAttribute('target')) {
                $link->removeAttribute('target');
                $stats['targets_removed']++;
            }

            // Clean rel attribute
            if ($link->hasAttribute('rel')) {
                $rel = $link->getAttribute('rel');
                $parts = array_filter(array_map('trim', explode(' ', $rel)));
                $parts = array_diff($parts, ['noopener', 'noreferrer']);

                if (empty($parts)) {
                    $link->removeAttribute('rel');
                    $stats['rels_cleaned']++;
                } else {
                    $link->setAttribute('rel', implode(' ', $parts));
                    // Count if we removed noopener/noreferrer
                    $originalParts = array_filter(array_map('trim', explode(' ', $rel)));
                    if (count($originalParts) > count($parts)) {
                        $stats['rels_cleaned']++;
                    }
                }
            }
        }

        // Get cleaned HTML from the container div
        $container = $dom->getElementById('rich-text-container');

        if ($container) {
            // Extract inner HTML from container
            $cleanedHtml = '';
            foreach ($container->childNodes as $child) {
                $cleanedHtml .= $dom->saveHTML($child);
            }
        } else {
            // Fallback: get all HTML and remove wrapper
            $cleanedHtml = $dom->saveHTML();
            // Remove XML declaration
            $cleanedHtml = preg_replace('/<\?xml encoding="UTF-8"\?>/', '', $cleanedHtml);
            // Try to extract body content if present
            if (preg_match('/<body[^>]*>(.*?)<\/body>/s', $cleanedHtml, $matches)) {
                $cleanedHtml = $matches[1];
            }
            // Remove wrapper div if present
            $cleanedHtml = preg_replace('/<div[^>]*id="rich-text-container"[^>]*>(.*?)<\/div>/s', '$1', $cleanedHtml);
        }

        return [
            'cleaned_html' => trim($cleanedHtml),
            'stats' => $stats,
        ];
    }
}
