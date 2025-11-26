<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
            // Check if shell_exec is available
            if (!function_exists('shell_exec')) {
                Log::error('shell_exec is not available on this server');
                return response()->json([
                    'success' => false,
                    'message' => 'Server configuration error: shell_exec is disabled.'
                ], 500);
            }

            // Use Node.js script with Puppeteer to extract MP3 URL
            $scriptPath = base_path('extract-mp3.js');
            
            // Check if script file exists
            if (!file_exists($scriptPath)) {
                Log::error('extract-mp3.js not found at: ' . $scriptPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Extraction script not found on server.'
                ], 500);
            }

            // Try to find node executable
            $nodePath = $this->findNodeExecutable();
            if (!$nodePath) {
                Log::error('Node.js executable not found');
                return response()->json([
                    'success' => false,
                    'message' => 'Node.js is not available on this server.'
                ], 500);
            }

            $escapedUrl = escapeshellarg($url);
            $escapedScriptPath = escapeshellarg($scriptPath);
            
            // Execute the Node.js script with timeout
            $command = "{$nodePath} {$escapedScriptPath} {$escapedUrl} 2>&1";
            Log::info('Executing command: ' . $command);
            
            $output = shell_exec($command);
            
            if ($output === null) {
                Log::error('shell_exec returned null. Command may have failed or timed out.');
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to execute extraction script. Please check server logs.'
                ], 500);
            }
            
            if (empty(trim($output))) {
                Log::error('Node.js script returned empty output');
                return response()->json([
                    'success' => false,
                    'message' => 'Extraction script returned no output.'
                ], 500);
            }
            
            // Parse the JSON output from the Node.js script
            $result = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Node.js output', [
                    'output' => $output,
                    'json_error' => json_last_error_msg()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse extraction result: ' . json_last_error_msg()
                ], 500);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Exception in extractMp3Url', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find Node.js executable path.
     */
    private function findNodeExecutable(): ?string
    {
        // Try common node paths
        $possiblePaths = [
            'node',
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/opt/homebrew/bin/node',
            env('NODE_PATH', null),
        ];

        foreach ($possiblePaths as $path) {
            if ($path && is_executable($path)) {
                return $path;
            }
            
            // Try to execute and check if it works
            if ($path) {
                $testOutput = shell_exec("{$path} --version 2>&1");
                if ($testOutput && strpos($testOutput, 'v') === 0) {
                    return $path;
                }
            }
        }

        // Last resort: try 'which node' or 'whereis node'
        $whichNode = shell_exec('which node 2>/dev/null');
        if ($whichNode && is_executable(trim($whichNode))) {
            return trim($whichNode);
        }

        return null;
    }
}
