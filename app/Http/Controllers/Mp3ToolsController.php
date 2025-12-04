<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PHPHtmlParser\Dom;

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
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');

        try {
            Log::info('Extracting MP3 URL from: ' . $url);

            // Create DOM parser
            $dom = new Dom();
            
            // Set options as an array (not Options object)
            // We need to keep scripts to extract __NEXT_DATA__
            $options = [
                'removeScripts' => false,
                'removeStyles' => true,
                'whitespaceTextNode' => false,
                // Custom curl headers to mimic a browser
                'curlHeaders' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                ],
            ];

            // Load the URL - the default Curl class will use the headers from options
            $dom->loadFromUrl($url, $options);

            // Find the __NEXT_DATA__ script tag by ID
            // The script tag has id="__NEXT_DATA__"
            $nextDataScript = $dom->find('#__NEXT_DATA__');
            
            if (empty($nextDataScript) || count($nextDataScript) === 0) {
                // Try alternative: find all script tags and look for the one with id __NEXT_DATA__
                $allScripts = $dom->find('script');
                $nextDataScript = [];
                foreach ($allScripts as $script) {
                    if ($script->getAttribute('id') === '__NEXT_DATA__') {
                        $nextDataScript[] = $script;
                        break;
                    }
                }
            }
            
            if (empty($nextDataScript) || count($nextDataScript) === 0) {
                Log::error('__NEXT_DATA__ script tag not found on page');
                return response()->json([
                    'success' => false,
                    'message' => 'Could not find episode data on the page. The page may not have loaded correctly.'
                ], 500);
            }

            // Get the first matching script tag
            $script = $nextDataScript[0];
            
            // Get the inner HTML/text content of the script tag
            // This contains the JSON data
            $jsonContent = $script->innerHtml;
            
            // If innerHtml is empty, try text content
            if (empty(trim($jsonContent))) {
                $jsonContent = $script->text;
            }

            if (empty($jsonContent)) {
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
                    'content_preview' => substr($jsonContent, 0, 200)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse episode data: ' . json_last_error_msg()
                ], 500);
            }

            // Extract the audio URL from the nested structure
            // Based on the Node.js script: nextData.props.pageProps.episode.audioFile?.url
            if (!isset($nextData['props']['pageProps']['episode']['audioFile']['url'])) {
                Log::error('Audio file URL not found in episode data', [
                    'available_keys' => array_keys($nextData['props']['pageProps']['episode'] ?? [])
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No audioFile URL found in the episode data.'
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
                'trace' => $e->getTraceAsString(),
                'url' => $url
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while extracting the MP3 URL: ' . $e->getMessage()
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
