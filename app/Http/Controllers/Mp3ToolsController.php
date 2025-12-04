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
        try {
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

            try {
                $scriptPath = base_path('extract-mp3.js');
                $diagnostics['script_path'] = $scriptPath;
                
                if (file_exists($scriptPath)) {
                    $diagnostics['script_exists'] = true;
                    $diagnostics['script_readable'] = is_readable($scriptPath);
                } else {
                    $diagnostics['errors'][] = 'extract-mp3.js not found at: ' . $scriptPath;
                }
            } catch (\Exception $e) {
                $diagnostics['errors'][] = 'Error checking script path: ' . $e->getMessage();
            }

            try {
                $nodePath = $this->findNodeExecutable();
                if ($nodePath) {
                    $diagnostics['node_found'] = true;
                    $diagnostics['node_path'] = $nodePath;
                    
                    if (function_exists('shell_exec')) {
                        $version = @shell_exec("{$nodePath} --version 2>&1");
                        if ($version) {
                            $diagnostics['node_version'] = trim($version);
                        }
                    }
                } else {
                    $diagnostics['errors'][] = 'Node.js not found';
                }
            } catch (\Exception $e) {
                $diagnostics['errors'][] = 'Error finding Node.js: ' . $e->getMessage();
            }

            try {
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
                    $diagnostics['errors'][] = 'Puppeteer not installed at: ' . $puppeteerPath;
                }
            } catch (\Exception $e) {
                $diagnostics['errors'][] = 'Error checking Puppeteer: ' . $e->getMessage();
            }

            try {
                $nodeModulesPath = base_path('node_modules');
                if (is_dir($nodeModulesPath)) {
                    $diagnostics['node_modules_readable'] = is_readable($nodeModulesPath);
                } else {
                    $diagnostics['errors'][] = 'node_modules directory not found at: ' . $nodeModulesPath;
                }
            } catch (\Exception $e) {
                $diagnostics['errors'][] = 'Error checking node_modules: ' . $e->getMessage();
            }

            // Test execution if possible
            if ($diagnostics['node_found'] && $diagnostics['script_exists'] && function_exists('shell_exec')) {
                try {
                    $testUrl = 'https://example.com';
                    $escapedUrl = escapeshellarg($testUrl);
                    $escapedScriptPath = escapeshellarg($scriptPath);
                    $command = "{$nodePath} {$escapedScriptPath} {$escapedUrl} 2>&1";
                    
                    $output = @shell_exec($command);
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
                        $diagnostics['errors'][] = 'Test execution failed - command returned null';
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

        } catch (\Exception $e) {
            Log::error('Exception in diagnose method', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Diagnostic error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'errors' => ['Exception occurred: ' . $e->getMessage()],
                'warnings' => []
            ], 500);
        }
    }

    /**
     * Extract MP3 URL from Zencastr URL.
     * Tries PHP HTML parser first (faster), falls back to Node.js/Puppeteer if needed.
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

            // Try PHP HTML parser first (faster, no JavaScript execution needed if SSR works)
            $phpResult = $this->extractWithPhpParser($url);
            
            if ($phpResult['success']) {
                Log::info('Successfully extracted MP3 URL using PHP parser: ' . $phpResult['audioUrl']);
                return response()->json([
                    'success' => true,
                    'audioUrl' => $phpResult['audioUrl'],
                    'message' => 'MP3 URL extracted successfully!'
                ]);
            }

            // If PHP parser failed, log the reason and try Puppeteer fallback
            Log::warning('PHP parser extraction failed, trying Puppeteer fallback', [
                'php_error' => $phpResult['message'] ?? 'Unknown error'
            ]);

            // Fallback to Node.js/Puppeteer script
            $puppeteerResult = $this->extractWithPuppeteer($url);
            
            if ($puppeteerResult['success']) {
                Log::info('Successfully extracted MP3 URL using Puppeteer: ' . $puppeteerResult['audioUrl']);
                return response()->json([
                    'success' => true,
                    'audioUrl' => $puppeteerResult['audioUrl'],
                    'message' => 'MP3 URL extracted successfully!'
                ]);
            }

            // Both methods failed
            Log::error('Both PHP parser and Puppeteer extraction failed', [
                'php_error' => $phpResult['message'] ?? 'Unknown error',
                'puppeteer_error' => $puppeteerResult['message'] ?? 'Unknown error'
            ]);

            return response()->json([
                'success' => false,
                'message' => $puppeteerResult['message'] ?? 'Failed to extract MP3 URL. Please check the URL and try again.'
            ], 500);

        } catch (\Exception $e) {
            Log::error('Exception in extractMp3Url', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'url' => $url ?? 'not set'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while extracting the MP3 URL: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract MP3 URL using PHP HTML parser (paquettg/php-html-parser).
     * This method tries to parse the HTML directly without JavaScript execution.
     */
    private function extractWithPhpParser(string $url): array
    {
        try {
            // Check if PHPHtmlParser is available
            if (!class_exists(\PHPHtmlParser\Dom::class)) {
                return [
                    'success' => false,
                    'message' => 'PHP HTML parser library not available'
                ];
            }

            // Fetch HTML using Guzzle
            $client = new Client([
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                ],
            ]);

            $response = $client->get($url);
            $html = $response->getBody()->getContents();

            // Parse HTML with PHPHtmlParser
            $dom = new Dom();
            $dom->loadStr($html);

            // Find the __NEXT_DATA__ script tag
            $nextDataScript = $dom->find('#__NEXT_DATA__');
            
            if (empty($nextDataScript) || count($nextDataScript) === 0) {
                // Try finding all script tags and look for __NEXT_DATA__
                $allScripts = $dom->find('script');
                foreach ($allScripts as $script) {
                    try {
                        $id = $script->getAttribute('id');
                        if ($id === '__NEXT_DATA__') {
                            $nextDataScript = [$script];
                            break;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            if (empty($nextDataScript) || count($nextDataScript) === 0) {
                return [
                    'success' => false,
                    'message' => '__NEXT_DATA__ script tag not found in HTML (may require JavaScript execution)'
                ];
            }

            // Get script content
            $script = $nextDataScript[0];
            try {
                $jsonContent = $script->innerHtml;
            } catch (\Exception $e) {
                $jsonContent = $script->text;
            }

            if (empty(trim($jsonContent))) {
                return [
                    'success' => false,
                    'message' => '__NEXT_DATA__ script tag is empty'
                ];
            }

            // Parse JSON
            $nextData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'message' => 'Failed to parse __NEXT_DATA__ JSON: ' . json_last_error_msg()
                ];
            }

            // Extract audio URL
            if (!isset($nextData['props']['pageProps']['episode']['audioFile']['url'])) {
                return [
                    'success' => false,
                    'message' => 'Audio file URL not found in episode data structure'
                ];
            }

            $audioUrl = $nextData['props']['pageProps']['episode']['audioFile']['url'];

            if (empty($audioUrl)) {
                return [
                    'success' => false,
                    'message' => 'Audio file URL is empty'
                ];
            }

            return [
                'success' => true,
                'audioUrl' => $audioUrl
            ];

        } catch (\Exception $e) {
            Log::error('PHP parser extraction failed', [
                'message' => $e->getMessage(),
                'url' => $url
            ]);
            return [
                'success' => false,
                'message' => 'PHP parser error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract MP3 URL using Node.js/Puppeteer script (fallback method).
     * This method executes JavaScript to render the page fully.
     */
    private function extractWithPuppeteer(string $url): array
    {
        try {
            // Check if shell_exec is available
            if (!function_exists('shell_exec')) {
                return [
                    'success' => false,
                    'message' => 'shell_exec function is not available'
                ];
            }

            // Find Node.js executable
            $nodePath = $this->findNodeExecutable();
            if (!$nodePath) {
                return [
                    'success' => false,
                    'message' => 'Node.js is not installed or not found on the server'
                ];
            }

            // Check if extract-mp3.js script exists
            $scriptPath = base_path('extract-mp3.js');
            if (!file_exists($scriptPath) || !is_readable($scriptPath)) {
                return [
                    'success' => false,
                    'message' => 'extract-mp3.js script not found or not readable'
                ];
            }

            // Execute the Node.js script
            $escapedUrl = escapeshellarg($url);
            $escapedScriptPath = escapeshellarg($scriptPath);
            $command = "{$nodePath} {$escapedScriptPath} {$escapedUrl} 2>&1";
            
            Log::info('Executing Puppeteer script', [
                'node_path' => $nodePath,
                'url' => $url
            ]);

            $output = shell_exec($command);

            if ($output === null) {
                return [
                    'success' => false,
                    'message' => 'Script execution returned null'
                ];
            }

            // Parse JSON output
            $result = json_decode(trim($output), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Puppeteer script output', [
                    'json_error' => json_last_error_msg(),
                    'output_preview' => substr($output, 0, 500)
                ]);
                return [
                    'success' => false,
                    'message' => 'Failed to parse script output: ' . json_last_error_msg()
                ];
            }

            if (isset($result['success']) && !$result['success']) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Puppeteer extraction failed'
                ];
            }

            if (!isset($result['audioUrl']) || empty($result['audioUrl'])) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'No audio URL found in the episode'
                ];
            }

            return [
                'success' => true,
                'audioUrl' => $result['audioUrl']
            ];

        } catch (\Exception $e) {
            Log::error('Puppeteer extraction failed', [
                'message' => $e->getMessage(),
                'url' => $url
            ]);
            return [
                'success' => false,
                'message' => 'Puppeteer error: ' . $e->getMessage()
            ];
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
