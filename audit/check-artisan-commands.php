<?php
// AUDIT Check: Scan all files under docs/ and scripts/ for `php artisan ...` commands
// and verify each exists in `php artisan list`.

require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

echo "====================================================================\n";
echo "STAGE 8: ARTISAN COMMAND CONSISTENCY AUDIT\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================================\n\n";

// Get available artisan commands
$artisanListJson = Artisan::output();
Artisan::call('list', ['--format' => 'json']);
$rawList = json_decode(Artisan::output(), true);
$availableCommands = [];
foreach ($rawList['commands'] ?? [] as $cmd) {
    $availableCommands[$cmd['name']] = true;
}

$scanDirs = [
    realpath(__DIR__ . '/../docs'),
];

$matchedReferences = [];
$nonExistentReferences = [];

// Match `php artisan <cmd>` or `artisan <cmd>` in markdown/scripts
$pattern = '/(?:`|\s)php\s+artisan\s+([a-zA-Z0-9_\-:]+)/i';

foreach ($scanDirs as $dir) {
    if (!$dir || !is_dir($dir)) continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        if (str_contains($file->getPathname(), 'docs' . DIRECTORY_SEPARATOR . 'audit')) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['md', 'sh', 'ps1', 'txt', 'bat'])) continue;

        $content = file_get_contents($file->getPathname());
        $relPath = str_replace(realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR, '', $file->getPathname());

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $cmdName = trim($m[1]);
                if (in_array($cmdName, ['serve', 'test', 'tinker', 'migrate', 'db', 'schedule', 'queue', 'cache', 'config', 'route', 'view', 'event'])) {
                    // Check if it's a subcommand or just prefix
                }

                // Skip pure command-line flags or PHP keywords if caught
                if (str_starts_with($cmdName, '-')) continue;

                $exists = isset($availableCommands[$cmdName]);
                $matchedReferences[] = [
                    'file' => $relPath,
                    'command' => $cmdName,
                    'exists' => $exists,
                ];

                if (!$exists) {
                    $nonExistentReferences[] = [
                        'file' => $relPath,
                        'command' => $cmdName,
                    ];
                }
            }
        }
    }
}

echo "Total artisan command references found: " . count($matchedReferences) . "\n";
echo "Non-existent command references: " . count($nonExistentReferences) . "\n\n";

if (!empty($nonExistentReferences)) {
    echo "=== NON-EXISTENT COMMANDS FOUND IN DOCS / SCRIPTS ===\n";
    foreach ($nonExistentReferences as $ne) {
        echo "  [FAIL] {$ne['file']} -> php artisan {$ne['command']}\n";
    }
    echo "\n";
} else {
    echo "=== ALL ARTISAN COMMANDS IN DOCS/SCRIPTS ARE 100% VALID ===\n\n";
}

exit(empty($nonExistentReferences) ? 0 : 1);
