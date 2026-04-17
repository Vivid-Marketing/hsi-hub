# Rich Text Tools - Laravel Hub Implementation Plan

## Overview

A new section in the Laravel hub app for cleaning and processing rich text content. This tool allows content editors to paste HTML from Craft CMS (or other sources) and clean it up before pasting it back.

## Problem Statement

When pasting content from Microsoft Word or other sources into Craft CMS Redactor fields, links often come with unwanted attributes:
- `target="_blank"` - Forces links to open in new tabs
- `rel="noopener noreferrer"` - Security attributes that aren't needed for same-tab links

We attempted to fix this with a custom Redactor plugin, but Redactor applies its own link handling after our code runs, making it unreliable. Since we're migrating to Craft 5 (which uses Hyper/CKEditor instead of Redactor), we're implementing this as a Laravel middleware tool instead.

## Solution

A web-based tool in the Laravel hub app where editors can:
1. Paste HTML content
2. Run cleaning actions (starting with link cleaning)
3. Copy the cleaned HTML back to Craft

## Project Structure

```
routes/web.php
  → GET /rich-text-tools (show editor)
  → POST /rich-text-tools/clean-links (process & return cleaned HTML)

app/Http/Controllers/RichTextToolsController.php
app/Services/RichTextCleanerService.php
resources/views/rich-text-tools/index.blade.php
```

## Dependencies

Install one of these for HTML parsing/cleaning:

**Recommended: `symfony/dom-crawler`**
```bash
composer require symfony/dom-crawler
```
- Already available via Laravel's `symfony/css-selector` dependency
- Robust HTML parsing and manipulation
- Well-maintained Symfony component

**Alternative: `masterminds/html5`**
```bash
composer require masterminds/html5
```
- HTML5 parser
- Good for modern HTML content

**Alternative: Native PHP `DOMDocument`**
- Built into PHP, no package needed
- More verbose API but works well

## Features

### Phase 1 (Initial Release)
- ✅ Rich text editor (textarea or simple WYSIWYG)
- ✅ Paste HTML content
- ✅ "Clean Links Window Target" action button
- ✅ Display cleaned HTML output (copyable)
- ✅ Copy-to-clipboard button

### Phase 2 (Future Actions)
- Clean empty paragraphs
- Remove inline styles
- Normalize headings
- Strip Word-specific markup
- Character count / word count
- Clean up nested lists
- Remove empty tags

## Implementation Details

### Service Layer (`RichTextCleanerService.php`)

```php
namespace App\Services;

class RichTextCleanerService
{
    /**
     * Remove target="_blank" and rel="noopener/noreferrer" from all links
     */
    public function cleanLinks($html)
    {
        // Parse HTML using chosen library
        // Find all <a> tags
        // Remove target attribute
        // Remove noopener/noreferrer from rel attribute
        // Return cleaned HTML
    }
}
```

### Controller (`RichTextToolsController.php`)

**Methods:**
- `index()` - Show editor form
- `cleanLinks()` - POST endpoint, return JSON with cleaned HTML

**Response Format:**
```json
{
    "success": true,
    "cleaned_html": "<a href=\"...\">Link</a>",
    "stats": {
        "links_cleaned": 5,
        "targets_removed": 3,
        "rels_cleaned": 2
    }
}
```

### View (`resources/views/rich-text-tools/index.blade.php`)

**Layout:**
```
┌─────────────────────────────────┐
│ Rich Text Tools                 │
├─────────────────────────────────┤
│ [Paste HTML here...]            │
│                                 │
│ <textarea id="input">           │
│                                 │
│ </textarea>                     │
│                                 │
├─────────────────────────────────┤
│ Actions:                        │
│ [Clean Links Window Target]     │
│                                 │
│ (Future: [Clean Empty P], etc)  │
├─────────────────────────────────┤
│ Cleaned Output:                 │
│ [Copy]                          │
│ <textarea id="output" readonly> │
│ </textarea>                     │
└─────────────────────────────────┘
```

## Navigation

Add to existing navigation component/view:

```php
// In your nav component/view
<li>
    <a href="{{ route('rich-text-tools.index') }}">Rich Text Tools</a>
</li>
```

## Routes

```php
// routes/web.php

Route::prefix('rich-text-tools')->name('rich-text-tools.')->group(function () {
    Route::get('/', [RichTextToolsController::class, 'index'])->name('index');
    Route::post('/clean-links', [RichTextToolsController::class, 'cleanLinks'])->name('clean-links');
});
```

## Link Cleaning Logic

The `cleanLinks()` method should:

1. **Parse HTML** - Use chosen library to parse the HTML string
2. **Find all `<a>` tags** - Query for all anchor elements
3. **Remove `target` attribute** - Remove any `target` attribute (especially `target="_blank"`)
4. **Clean `rel` attribute**:
   - If `rel` contains only `noopener` and/or `noreferrer`, remove the attribute entirely
   - If `rel` contains other values (e.g., `nofollow`), remove only `noopener` and `noreferrer`, keep the rest
5. **Return cleaned HTML** - Output the modified HTML string

## Example Implementation (using Symfony DOMCrawler)

```php
use Symfony\Component\DomCrawler\Crawler;

public function cleanLinks($html)
{
    $crawler = new Crawler($html);
    
    $crawler->filter('a')->each(function (Crawler $node) {
        // Remove target attribute
        $node->getNode(0)->removeAttribute('target');
        
        // Clean rel attribute
        $rel = $node->attr('rel');
        if ($rel) {
            $parts = array_filter(array_map('trim', explode(' ', $rel)));
            $parts = array_diff($parts, ['noopener', 'noreferrer']);
            
            if (empty($parts)) {
                $node->getNode(0)->removeAttribute('rel');
            } else {
                $node->getNode(0)->setAttribute('rel', implode(' ', $parts));
            }
        }
    });
    
    return $crawler->html();
}
```

## Testing

Test cases to verify:

1. **Simple link**: `<a href="...">Link</a>` → No change
2. **Link with target**: `<a href="..." target="_blank">Link</a>` → `<a href="...">Link</a>`
3. **Link with rel**: `<a href="..." rel="noopener">Link</a>` → `<a href="...">Link</a>`
4. **Link with both**: `<a href="..." target="_blank" rel="noopener noreferrer">Link</a>` → `<a href="...">Link</a>`
5. **Link with other rel**: `<a href="..." rel="nofollow noopener">Link</a>` → `<a href="..." rel="nofollow">Link</a>`
6. **Multiple links**: Should clean all links in the HTML
7. **Nested content**: Should preserve all other HTML structure

## Future Enhancements

- **Batch processing** - Process multiple HTML snippets at once
- **Preview mode** - Show before/after comparison
- **Undo/Redo** - Allow multiple cleaning operations with undo
- **Export options** - Download cleaned HTML as file
- **History** - Save recent cleaning operations
- **Templates** - Pre-defined cleaning profiles for different use cases

## Migration Notes

This tool is temporary until Craft 5 migration is complete. Once migrated to Craft 5 with Hyper/CKEditor, we can:
- Evaluate if CKEditor has better link handling
- Consider implementing similar cleaning in Craft 5 if needed
- Or continue using this Laravel tool if it proves useful for other content cleaning needs
