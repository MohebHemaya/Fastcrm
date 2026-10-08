<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class DocumentationArtisanCommandsTest extends TestCase
{
    public function test_all_artisan_commands_in_docs_and_scripts_exist(): void
    {
        $rootDir = dirname(base_path());
        $allCommands = array_keys(Artisan::all());
        $allCommands[] = 'test';
        $allCommands[] = 'tinker';

        $files = [];
        $docsDir = $rootDir.DIRECTORY_SEPARATOR.'docs';
        if (is_dir($docsDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($docsDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $scriptsDir = $rootDir.DIRECTORY_SEPARATOR.'scripts';
        if (is_dir($scriptsDir)) {
            $iteratorScripts = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($scriptsDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iteratorScripts as $file) {
                if ($file->isFile() && $file->getFilename() !== 'check-artisan-docs.php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $patterns = [
            '/(?:php|\/usr\/bin\/php|\$PHP_BIN)\s+artisan\s+([a-zA-Z0-9\-_:]+)/i',
            '/`artisan\s+([a-zA-Z0-9\-_:]+)`/i',
            '/^\s*(?:[-*]|\d+\.|\>|\|)?\s*`?artisan\s+([a-zA-Z0-9\-_:]+)/i',
        ];

        $ignoreWords = [
            'command',
            'commands',
            'composer',
            'vendor',
            'artisan',
        ];

        $errors = [];
        foreach ($files as $filePath) {
            $relativePath = str_replace($rootDir.DIRECTORY_SEPARATOR, '', $filePath);
            $lines = file($filePath, FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($lines as $lineNum => $line) {
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $line, $matches)) {
                        foreach ($matches[1] as $cmd) {
                            $cmd = trim($cmd, '`"\'');
                            if (in_array(strtolower($cmd), $ignoreWords, true) || str_starts_with($cmd, '$') || str_starts_with($cmd, '-')) {
                                continue;
                            }

                            if (! in_array($cmd, $allCommands, true)) {
                                $errors[] = "[$relativePath:".($lineNum + 1)."] Unknown Artisan command referenced: '$cmd'";
                            }
                        }
                    }
                }
            }
        }

        $this->assertEmpty($errors, "Found invalid artisan command references in docs/scripts:\n".implode("\n", $errors));
    }
}
