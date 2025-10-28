<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class Mp3ToolsController extends Controller
{
    /**
     * Show the MP3 Tools page.
     */
    public function index()
    {
        return view('mp3-tools.index');
    }

    /**
     * Extract MP3 URL from Zencastr URL.
     */
    public function extractMp3Url(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');

        try {
            // Use Node.js script with Puppeteer to extract MP3 URL
            $scriptPath = base_path('extract-mp3.js');
            $escapedUrl = escapeshellarg($url);
            
            // Execute the Node.js script
            $output = shell_exec("node {$scriptPath} {$escapedUrl} 2>&1");
            
            if (!$output) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to execute extraction script.'
                ], 500);
            }
            
            // Parse the JSON output from the Node.js script
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error('Failed to parse Node.js output: ' . $output);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse extraction result.'
                ], 500);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
