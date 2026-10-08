<?php

declare(strict_types=1);

/**
 * Validates that all "php artisan <cmd>" or "`artisan <cmd>`" references in docs/ and scripts/
 * exist in the application's registered Artisan commands.
 */

$rootDir = dirname(__DIR__);
$backendDir = $rootDir . DIRECTORY_SEPARATOR . 'backend';

// 1. Get registered artisan commands from backend
require $backendDir . '/vendor/autoload.php';
$app = require_once $backendDir . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$artisan = $app->make(\Illuminate\Contracts\Console\Kernel::class)->all();
$validCommands = array_keys($artisan);

// Add standard Laravel built-ins or aliases if not directly in all()
$builtInAliases = [
    'test',
    'tinker',
];
$validCommands = array_unique(array_merge($validCommands, $builtInAliases));

// 2. Collect files under docs/ and scripts/
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir . DIRECTORY_SEPARATOR . 'docs', RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'md') {
        $files[] = $file->getPathname();
    }
}

$iteratorScripts = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir . DIRECTORY_SEPARATOR . 'scripts', RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iteratorScripts as $file) {
    if ($file->isFile()) {
        $files[] = $file->getPathname();
    }
}

$errors = [];
$checkedCount = 0;

// Patterns matching command invocations:
// 1. php artisan <cmd>
// 2. `artisan <cmd>`
// 3. Command starting after line/bullet/pipe: e.g. `artisan <cmd>`
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

foreach ($files as $filePath) {
    $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $filePath);
    // Skip self
    if (basename($filePath) === 'check-artisan-docs.php') {
        continue;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $lineNum => $line) {
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $line, $matches)) {
                foreach ($matches[1] as $cmd) {
                    $cmd = trim($cmd, '`"\'');
                    if (in_array(strtolower($cmd), $ignoreWords, true) || str_starts_with($cmd, '$') || str_starts_with($cmd, '-')) {
                        continue;
                    }

                    $checkedCount++;
                    if (!in_array($cmd, $validCommands, true)) {
                        $errors[] = sprintf(
                            "[%s:%d] Unknown Artisan command referenced: '%s'",
                            $relativePath,
                            $lineNum + 1,
                            $cmd
                        );
                    }
                }
            }
        }
    }
}

echo "===========================================\n";
echo "Artisan Documentation Consistency Check\n";
echo "===========================================\n";
echo sprintf("Scanned %d artisan command references across %d files.\n", $checkedCount, count($files));

if (count($errors) > 0) {
    echo "\nFAILED: Found " . count($errors) . " invalid artisan command reference(s):\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
    echo "\nPlease fix the documentation or register the missing Artisan command.\n";
    exit(1);
}

echo "PASSED: All referenced artisan commands exist in the application.\n";
exit(0);
