<?php

namespace App\Http\Controllers;

use App\Services\RichTextCleanerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RichTextToolsController extends Controller
{
    protected RichTextCleanerService $cleanerService;

    public function __construct(RichTextCleanerService $cleanerService)
    {
        $this->cleanerService = $cleanerService;
    }

    /**
     * Show the Rich Text Tools editor page.
     */
    public function index(): View
    {
        return view('rich-text-tools.index');
    }

    /**
     * Clean links in HTML content.
     */
    public function cleanLinks(Request $request): JsonResponse
    {
        $request->validate([
            'html' => 'required|string',
        ]);

        try {
            $html = $request->input('html');
            $result = $this->cleanerService->cleanLinks($html);

            return response()->json([
                'success' => true,
                'cleaned_html' => $result['cleaned_html'],
                'stats' => $result['stats'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while cleaning the HTML: '.$e->getMessage(),
            ], 500);
        }
    }
}
