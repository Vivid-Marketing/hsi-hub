<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiagnoseMp3Tools extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mp3-tools:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose MP3 extraction tool setup and requirements';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Diagnosing MP3 Tools Setup...');
        $this->newLine();

        $issues = [];
        $warnings = [];

        // Check shell_exec availability
        $this->info('1. Checking shell_exec availability...');
        if (function_exists('shell_exec')) {
            $this->line('   ✅ shell_exec is available');
        } else {
            $this->error('   ❌ shell_exec is NOT available');
            $issues[] = 'shell_exec function is disabled';
        }
        $this->newLine();

        // Check extract-mp3.js file
        $this->info('2. Checking extract-mp3.js file...');
        $scriptPath = base_path('extract-mp3.js');
        if (file_exists($scriptPath)) {
            $this->line('   ✅ File exists at: ' . $scriptPath);
            
            if (is_readable($scriptPath)) {
                $this->line('   ✅ File is readable');
            } else {
                $this->error('   ❌ File is NOT readable');
                $issues[] = 'extract-mp3.js is not readable';
            }
            
            $perms = substr(sprintf('%o', fileperms($scriptPath)), -4);
            $this->line('   📋 File permissions: ' . $perms);
        } else {
            $this->error('   ❌ File NOT found at: ' . $scriptPath);
            $issues[] = 'extract-mp3.js file not found';
        }
        $this->newLine();

        // Check Node.js
        $this->info('3. Checking Node.js installation...');
        $nodePath = $this->findNodeExecutable();
        if ($nodePath) {
            $this->line('   ✅ Node.js found at: ' . $nodePath);
            
            $version = shell_exec("{$nodePath} --version 2>&1");
            if ($version) {
                $this->line('   📋 Version: ' . trim($version));
            }
            
            // Test if we can execute a simple command
            $testOutput = shell_exec("{$nodePath} -e 'console.log(\"OK\")' 2>&1");
            if (trim($testOutput) === 'OK') {
                $this->line('   ✅ Node.js is executable');
            } else {
                $this->error('   ❌ Node.js execution test failed');
                $issues[] = 'Node.js cannot execute commands';
            }
        } else {
            $this->error('   ❌ Node.js NOT found');
            $issues[] = 'Node.js is not installed or not in PATH';
        }
        $this->newLine();

        // Check if we can run the script (syntax check)
        if ($nodePath && file_exists($scriptPath)) {
            $this->info('4. Testing extract-mp3.js syntax...');
            $syntaxCheck = shell_exec("{$nodePath} --check {$scriptPath} 2>&1");
            if ($syntaxCheck === null || trim($syntaxCheck) === '') {
                $this->line('   ✅ Script syntax is valid');
            } else {
                $this->warn('   ⚠️  Syntax check output: ' . trim($syntaxCheck));
                $warnings[] = 'Script syntax check returned output';
            }
            $this->newLine();
        }

        // Check Puppeteer
        $this->info('5. Checking Puppeteer installation...');
        $puppeteerPath = base_path('node_modules/puppeteer');
        if (is_dir($puppeteerPath)) {
            $this->line('   ✅ Puppeteer directory exists');
            
            if (is_readable($puppeteerPath)) {
                $this->line('   ✅ Puppeteer directory is readable');
            } else {
                $this->error('   ❌ Puppeteer directory is NOT readable');
                $issues[] = 'Puppeteer directory is not readable';
            }
            
            // Check for browser binaries
            $browserPath = base_path('node_modules/puppeteer/.local-chromium');
            if (is_dir($browserPath)) {
                $this->line('   ✅ Browser binaries directory exists');
                
                // Try to find chrome executable
                $chromeFound = false;
                $possibleChrome = [
                    $browserPath . '/**/chrome',
                    $browserPath . '/**/chrome.exe',
                    $browserPath . '/**/chromium',
                ];
                
                foreach ($possibleChrome as $pattern) {
                    $files = glob($pattern);
                    if (!empty($files)) {
                        $chromeFound = true;
                        $chromePath = $files[0];
                        $this->line('   ✅ Chrome binary found: ' . basename(dirname($chromePath)));
                        
                        if (is_executable($chromePath)) {
                            $this->line('   ✅ Chrome binary is executable');
                        } else {
                            $this->error('   ❌ Chrome binary is NOT executable');
                            $issues[] = 'Chrome binary is not executable';
                        }
                        break;
                    }
                }
                
                if (!$chromeFound) {
                    $this->warn('   ⚠️  Chrome binary not found (may install on first use)');
                    $warnings[] = 'Chrome binary not found';
                }
            } else {
                $this->warn('   ⚠️  Browser binaries directory not found (may install on first use)');
                $warnings[] = 'Browser binaries not installed';
            }
        } else {
            $this->error('   ❌ Puppeteer NOT installed');
            $issues[] = 'Puppeteer is not installed in node_modules';
        }
        $this->newLine();

        // Check node_modules permissions
        $this->info('6. Checking node_modules permissions...');
        $nodeModulesPath = base_path('node_modules');
        if (is_dir($nodeModulesPath)) {
            if (is_readable($nodeModulesPath)) {
                $this->line('   ✅ node_modules is readable');
            } else {
                $this->error('   ❌ node_modules is NOT readable');
                $issues[] = 'node_modules directory is not readable';
            }
            
            $perms = substr(sprintf('%o', fileperms($nodeModulesPath)), -4);
            $this->line('   📋 Directory permissions: ' . $perms);
        } else {
            $this->error('   ❌ node_modules directory NOT found');
            $issues[] = 'node_modules directory not found';
        }
        $this->newLine();

        // Test actual script execution (dry run)
        if ($nodePath && file_exists($scriptPath)) {
            $this->info('7. Testing script execution (dry run)...');
            $testUrl = 'https://example.com';
            $escapedUrl = escapeshellarg($testUrl);
            $escapedScriptPath = escapeshellarg($scriptPath);
            $command = "{$nodePath} {$escapedScriptPath} {$escapedUrl} 2>&1";
            
            $this->line('   📋 Command: ' . $command);
            
            // Set a timeout
            $output = shell_exec($command);
            
            if ($output === null) {
                $this->error('   ❌ Script execution returned null');
                $issues[] = 'Script execution failed (returned null)';
            } else {
                $this->line('   ✅ Script executed (output length: ' . strlen($output) . ' bytes)');
                
                // Try to parse as JSON
                $result = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->line('   ✅ Output is valid JSON');
                    if (isset($result['success'])) {
                        $this->line('   📋 Result: success=' . ($result['success'] ? 'true' : 'false'));
                    }
                } else {
                    $this->warn('   ⚠️  Output is not valid JSON: ' . json_last_error_msg());
                    $this->line('   📋 First 200 chars of output: ' . substr($output, 0, 200));
                    $warnings[] = 'Script output is not valid JSON';
                }
            }
            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        
        if (empty($issues)) {
            $this->info('   ✅ No critical issues found!');
        } else {
            $this->error('   ❌ Found ' . count($issues) . ' critical issue(s):');
            foreach ($issues as $issue) {
                $this->line('      - ' . $issue);
            }
        }
        
        if (!empty($warnings)) {
            $this->warn('   ⚠️  Found ' . count($warnings) . ' warning(s):');
            foreach ($warnings as $warning) {
                $this->line('      - ' . $warning);
            }
        }

        return empty($issues) ? 0 : 1;
    }

    /**
     * Find Node.js executable path.
     */
    private function findNodeExecutable(): ?string
    {
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
            
            if ($path) {
                $testOutput = shell_exec("{$path} --version 2>&1");
                if ($testOutput && strpos($testOutput, 'v') === 0) {
                    return $path;
                }
            }
        }

        $whichNode = shell_exec('which node 2>/dev/null');
        if ($whichNode && is_executable(trim($whichNode))) {
            return trim($whichNode);
        }

        return null;
    }
}

