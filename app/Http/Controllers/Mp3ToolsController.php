<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PHPHtmlParser\Dom;
use GuzzleHttp\Client;

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
     * Diagnostic endpoint to check MP3 tools setup.
     */
    public function diagnose(): JsonResponse
    {
        $diagnostics = [
            'shell_exec_available' => function_exists('shell_exec'),
            'script_exists' => false,
            'script_path' => null,
            'script_readable' => false,
            'node_found' => false,
            'node_path' => null,
            'node_version' => null,
            'puppeteer_installed' => false,
            'puppeteer_readable' => false,
            'browser_binaries_found' => false,
            'node_modules_readable' => false,
            'test_execution' => null,
            'errors' => [],
            'warnings' => [],
        ];

        $scriptPath = base_path('extract-mp3.js');
        $diagnostics['script_path'] = $scriptPath;
        
        if (file_exists($scriptPath)) {
            $diagnostics['script_exists'] = true;
            $diagnostics['script_readable'] = is_readable($scriptPath);
        } else {
            $diagnostics['errors'][] = 'extract-mp3.js not found';
        }

        $nodePath = $this->findNodeExecutable();
        if ($nodePath) {
            $diagnostics['node_found'] = true;
            $diagnostics['node_path'] = $nodePath;
            $version = shell_exec("{$nodePath} --version 2>&1");
            if ($version) {
                $diagnostics['node_version'] = trim($version);
            }
        } else {
            $diagnostics['errors'][] = 'Node.js not found';
        }

        $puppeteerPath = base_path('node_modules/puppeteer');
        if (is_dir($puppeteerPath)) {
            $diagnostics['puppeteer_installed'] = true;
            $diagnostics['puppeteer_readable'] = is_readable($puppeteerPath);
            
            $browserPath = base_path('node_modules/puppeteer/.local-chromium');
            if (is_dir($browserPath)) {
                $diagnostics['browser_binaries_found'] = true;
            } else {
                $diagnostics['warnings'][] = 'Browser binaries not installed (may install on first use)';
            }
        } else {
            $diagnostics['errors'][] = 'Puppeteer not installed';
        }

        $nodeModulesPath = base_path('node_modules');
        if (is_dir($nodeModulesPath)) {
            $diagnostics['node_modules_readable'] = is_readable($nodeModulesPath);
        } else {
            $diagnostics['errors'][] = 'node_modules directory not found';
        }

        // Test execution if possible
        if ($diagnostics['node_found'] && $diagnostics['script_exists']) {
            try {
                $testUrl = 'https://example.com';
                $escapedUrl = escapeshellarg($testUrl);
                $escapedScriptPath = escapeshellarg($scriptPath);
                $command = "{$nodePath} {$escapedScriptPath} {$escapedUrl} 2>&1";
                
                $output = shell_exec($command);
                if ($output !== null) {
                    $diagnostics['test_execution'] = [
                        'success' => true,
                        'output_length' => strlen($output),
                        'is_json' => json_decode($output, true) !== null,
                        'output_preview' => substr($output, 0, 200),
                    ];
                } else {
                    $diagnostics['test_execution'] = [
                        'success' => false,
                        'error' => 'Command returned null',
                    ];
                    $diagnostics['errors'][] = 'Test execution failed';
                }
            } catch (\Exception $e) {
                $diagnostics['test_execution'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                $diagnostics['errors'][] = 'Test execution exception: ' . $e->getMessage();
            }
        }

        return response()->json($diagnostics);
    }

    /**
     * Extract MP3 URL from Zencastr URL using PHP HTML parser.
     */
    public function extractMp3Url(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'url' => 'required|url'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed in extractMp3Url', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid URL provided: ' . implode(', ', $e->errors()['url'] ?? [])
            ], 422);
        }

        $url = $request->input('url');

        try {
            Log::info('Extracting MP3 URL from: ' . $url);

            // Check if PHPHtmlParser is available
            if (!class_exists(\PHPHtmlParser\Dom::class)) {
                Log::error('PHPHtmlParser\Dom class not found');
                return response()->json([
                    'success' => false,
                    'message' => 'HTML parser library not available. Please check server configuration.'
                ], 500);
            }

            // Create DOM parser
            $dom = new Dom();
            
            try {
                // Load the URL with basic options
                // PHPHtmlParser v2 uses a simpler API
                $dom->loadFromUrl($url);
            } catch (\Exception $loadException) {
                Log::error('Failed to load URL in PHPHtmlParser', [
                    'message' => $loadException->getMessage(),
                    'trace' => $loadException->getTraceAsString(),
                    'url' => $url
                ]);
                
                // Try using Guzzle to fetch and then parse
                try {
                    $client = new Client();
                    $response = $client->get($url, [
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        ],
                        'timeout' => 30,
                    ]);
                    $html = $response->getBody()->getContents();
                    $dom->loadStr($html);
                } catch (\Exception $guzzleException) {
                    Log::error('Failed to load URL using Guzzle fallback', [
                        'message' => $guzzleException->getMessage(),
                        'url' => $url
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to load the page. Please check the URL and try again.'
                    ], 500);
                }
            }

            // Find the __NEXT_DATA__ script tag by ID
            $nextDataScript = $dom->find('#__NEXT_DATA__');
            
            // If not found by ID, try finding all script tags
            if (empty($nextDataScript) || count($nextDataScript) === 0) {
                $allScripts = $dom->find('script');
                $nextDataScript = [];
                foreach ($allScripts as $script) {
                    try {
                        $id = $script->getAttribute('id');
                        if ($id === '__NEXT_DATA__') {
                            $nextDataScript[] = $script;
                            break;
                        }
                    } catch (\Exception $e) {
                        // Continue searching if attribute access fails
                        continue;
                    }
                }
            }
            
            if (empty($nextDataScript) || count($nextDataScript) === 0) {
                Log::error('__NEXT_DATA__ script tag not found on page', ['url' => $url]);
                return response()->json([
                    'success' => false,
                    'message' => 'Could not find episode data on the page. The page may not have loaded correctly or the URL may be invalid.'
                ], 500);
            }

            // Get the first matching script tag
            $script = $nextDataScript[0];
            
            // Get the inner HTML/text content of the script tag
            try {
                $jsonContent = $script->innerHtml;
            } catch (\Exception $e) {
                try {
                    $jsonContent = $script->text;
                } catch (\Exception $e2) {
                    Log::error('Failed to get script content', [
                        'message1' => $e->getMessage(),
                        'message2' => $e2->getMessage()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to extract script content from the page.'
                    ], 500);
                }
            }
            
            // If still empty, try alternative methods
            if (empty(trim($jsonContent))) {
                try {
                    $jsonContent = $script->text;
                } catch (\Exception $e) {
                    Log::error('Script content is empty and text extraction failed', [
                        'message' => $e->getMessage()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Episode data script tag is empty.'
                    ], 500);
                }
            }

            if (empty(trim($jsonContent))) {
                Log::error('__NEXT_DATA__ script tag is empty');
                return response()->json([
                    'success' => false,
                    'message' => 'Episode data script tag is empty.'
                ], 500);
            }

            // Parse the JSON data
            $nextData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse __NEXT_DATA__ JSON', [
                    'json_error' => json_last_error_msg(),
                    'content_preview' => substr($jsonContent, 0, 200),
                    'content_length' => strlen($jsonContent)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse episode data: ' . json_last_error_msg()
                ], 500);
            }

            // Extract the audio URL from the nested structure
            // Check each level to provide better error messages
            if (!isset($nextData['props'])) {
                Log::error('props key not found in __NEXT_DATA__', [
                    'available_keys' => array_keys($nextData ?? [])
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid episode data structure: props not found.'
                ], 500);
            }

            if (!isset($nextData['props']['pageProps'])) {
                Log::error('pageProps key not found in __NEXT_DATA__');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid episode data structure: pageProps not found.'
                ], 500);
            }

            if (!isset($nextData['props']['pageProps']['episode'])) {
                Log::error('episode key not found in __NEXT_DATA__');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid episode data structure: episode not found.'
                ], 500);
            }

            if (!isset($nextData['props']['pageProps']['episode']['audioFile'])) {
                Log::error('audioFile key not found in episode data', [
                    'available_keys' => array_keys($nextData['props']['pageProps']['episode'] ?? [])
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No audioFile found in the episode data. The episode may not have an audio file.'
                ], 500);
            }

            if (!isset($nextData['props']['pageProps']['episode']['audioFile']['url'])) {
                Log::error('url key not found in audioFile');
                return response()->json([
                    'success' => false,
                    'message' => 'Audio file URL not found in the episode data.'
                ], 500);
            }

            $audioUrl = $nextData['props']['pageProps']['episode']['audioFile']['url'];

            if (empty($audioUrl)) {
                Log::error('Audio URL is empty');
                return response()->json([
                    'success' => false,
                    'message' => 'Audio file URL is empty.'
                ], 500);
            }

            Log::info('Successfully extracted MP3 URL: ' . $audioUrl);

            return response()->json([
                'success' => true,
                'audioUrl' => $audioUrl,
                'message' => 'MP3 URL extracted successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in extractMp3Url', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'url' => $url ?? 'not set'
            ]);
            
            // Return a user-friendly error message
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while extracting the MP3 URL. Please check the Laravel logs for details.'
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
