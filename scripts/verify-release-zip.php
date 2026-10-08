<?php

/**
 * Release Zip Verification Script
 *
 * Verifies that a generated release zip file strictly excludes:
 * - .env (any .env file)
 * - bootstrap/cache/*.php (cached services, packages, config)
 * - storage/logs/* (any log files)
 * - node_modules
 * - tests
 * - .git
 */

$zipPath = $argv[1] ?? __DIR__ . '/../release/release.zip';

if (!file_exists($zipPath)) {
    // Check root release.zip fallback
    $fallback = __DIR__ . '/../release.zip';
    if (file_exists($fallback)) {
        $zipPath = $fallback;
    } else {
        fwrite(STDERR, "ERROR: Release zip not found at {$zipPath}\n");
        exit(1);
    }
}

echo "--> Inspecting release zip: {$zipPath}\n";

$zip = new ZipArchive();
$res = $zip->open($zipPath);
if ($res !== true) {
    fwrite(STDERR, "ERROR: Failed to open zip file {$zipPath} (code {$res})\n");
    exit(1);
}

$forbiddenPatterns = [
    '/\.env($|\..*)/i' => '.env files',
    '/bootstrap\/cache\/.*\.php$/i' => 'bootstrap/cache/*.php compiled caches',
    '/storage\/logs\/.+\.log$/i' => 'storage/logs/*.log runtime log files',
    '/node_modules(\/|$)/i' => 'node_modules directories',
    '/tests(\/|$)/i' => 'tests directories or test files',
    '/\.git(\/|$)/i' => '.git version control metadata',
];

$violations = [];
$totalFiles = $zip->numFiles;

for ($i = 0; $i < $totalFiles; $i++) {
    $stat = $zip->statIndex($i);
    $name = str_replace('\\', '/', $stat['name']);

    foreach ($forbiddenPatterns as $pattern => $description) {
        if (preg_match($pattern, $name)) {
            $violations[] = "[{$description}] {$name}";
        }
    }
}

$zip->close();

if (!empty($violations)) {
    fwrite(STDERR, "\n=======================================================\n");
    fwrite(STDERR, "FAILED: Forbidden files found in release zip archive!\n");
    fwrite(STDERR, "=======================================================\n");
    foreach ($violations as $v) {
        fwrite(STDERR, "  - {$v}\n");
    }
    fwrite(STDERR, "\nTotal violations: " . count($violations) . "\n");
    exit(1);
}

echo "PASSED: Verified {$totalFiles} files in release zip. Zero forbidden files detected.\n";
exit(0);
