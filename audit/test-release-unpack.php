<?php
// AUDIT Stage 8: Production Release Package Unpack & Hostinger Smoke Test

$releaseZip = realpath(__DIR__ . '/../release/release.zip');
$stagingDir = __DIR__ . '/staging-test';

echo "====================================================================\n";
echo "STAGE 8: RELEASE PACKAGE UNPACK & SMOKE AUDIT\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Release archive: {$releaseZip}\n";
echo "====================================================================\n\n";

$results = [];
function recordStep(string $step, string $description, bool $passed, string $details): void {
    global $results;
    $results[] = compact('step', 'description', 'passed', 'details');
    echo sprintf("[%s] %-55s -> %s\n  %s\n\n",
        $step, $description, $passed ? "PASS" : "FAIL", $details);
}

// 1. Unpack to staging-test
if (is_dir($stagingDir)) {
    // Clean prior unpack
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($stagingDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir($stagingDir);
}
mkdir($stagingDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($releaseZip) === true) {
    $zip->extractTo($stagingDir);
    $zip->close();
}

$backendExists = is_dir($stagingDir . '/backend');
$publicHtmlExists = is_dir($stagingDir . '/public_html');
$indexPhpExists = file_exists($stagingDir . '/public_html/index.php');
$htaccessExists = file_exists($stagingDir . '/public_html/.htaccess');

$step1Passed = ($backendExists && $publicHtmlExists && $indexPhpExists && $htaccessExists);
recordStep('STEP_1', 'Release archive unpacks with Hostinger layout', $step1Passed,
    "backend: " . ($backendExists ? 'YES' : 'NO') . ", public_html: " . ($publicHtmlExists ? 'YES' : 'NO') . ", index.php: " . ($indexPhpExists ? 'YES' : 'NO') . ", .htaccess: " . ($htaccessExists ? 'YES' : 'NO'));

// 2. Audit .htaccess content
$htaccessContent = file_get_contents($stagingDir . '/public_html/.htaccess');
$hasWebhooksInRewrite = (bool) preg_match('/RewriteCond\s+%{REQUEST_URI}\s+\^\/\([^)]*webhooks[^)]*\)/i', $htaccessContent);

recordStep('STEP_2', 'Verify .htaccess routes /webhooks to Laravel index.php', $hasWebhooksInRewrite,
    $hasWebhooksInRewrite ? "Webhooks included in RewriteCond" : "CRITICAL DEFECT: RewriteCond %{REQUEST_URI} ^/(api|sanctum|up) omits webhooks! /webhooks/whatsapp falls through to SPA index.html!");

// 3. Inspect public_html/index.php path references
$indexPhpContent = file_get_contents($stagingDir . '/public_html/index.php');
$correctAutoload = str_contains($indexPhpContent, "__DIR__.'/../backend/vendor/autoload.php'");
$correctBootstrap = str_contains($indexPhpContent, "__DIR__.'/../backend/bootstrap/app.php'");

$step3Passed = ($correctAutoload && $correctBootstrap);
recordStep('STEP_3', 'public_html/index.php correctly points to ../backend/', $step3Passed,
    "autoload: " . ($correctAutoload ? 'YES' : 'NO') . ", bootstrap: " . ($correctBootstrap ? 'YES' : 'NO'));

// 4. Test production caching commands on main backend
$phpBin = 'C:\\Users\\Moheb Hemaya\\AppData\\Local\\Microsoft\\WinGet\\Packages\\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\\php.exe';
$backendDir = realpath(__DIR__ . '/../backend');
$artisan = $backendDir . '\\artisan';

exec("\"{$phpBin}\" \"{$artisan}\" config:cache", $configCacheOut, $configCacheExit);
exec("\"{$phpBin}\" \"{$artisan}\" route:cache", $routeCacheOut, $routeCacheExit);
exec("\"{$phpBin}\" \"{$artisan}\" view:cache", $viewCacheOut, $viewCacheExit);

// Clear them back after testing
exec("\"{$phpBin}\" \"{$artisan}\" config:clear");
exec("\"{$phpBin}\" \"{$artisan}\" route:clear");
exec("\"{$phpBin}\" \"{$artisan}\" view:clear");

$step4Passed = ($configCacheExit === 0 && $routeCacheExit === 0 && $viewCacheExit === 0);
recordStep('STEP_4', 'Production artisan optimize/cache commands (config, route, view)', $step4Passed,
    "config:cache exit: $configCacheExit, route:cache exit: $routeCacheExit, view:cache exit: $viewCacheExit");

// -----------------------------------------------------------------------------
// FINAL SUMMARY
// -----------------------------------------------------------------------------
$allPassed = !in_array(false, array_column($results, 'passed'), true);
echo "====================================================================\n";
echo "STAGE 8 SMOKE AUDIT RESULT: " . ($allPassed ? "100% PASS" : "FAIL (DEFECTS DETECTED)") . "\n";
echo "====================================================================\n";
