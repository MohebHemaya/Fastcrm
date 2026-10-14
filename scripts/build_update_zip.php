<?php
ini_set('memory_limit', '512M');
set_time_limit(300);

echo "=== BUILDING UNIVERSAL UPDATE.ZIP ===\n\n";

$base = dirname(__DIR__);
$releaseDir = $base . '/release';
$zipPath = $releaseDir . '/update.zip';

if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("ERROR: Could not open $zipPath for writing.\n");
}

function addFolderToZip($zip, $sourceDir, $prefix = '', $excludePatterns = []) {
    $sourceDir = rtrim(str_replace('\\', '/', realpath($sourceDir)), '/');
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $count = 0;
    foreach ($files as $file) {
        $filePath = str_replace('\\', '/', $file->getRealPath());
        $relPath = substr($filePath, strlen($sourceDir) + 1);

        $skip = false;
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $relPath)) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        $entryName = $prefix !== '' ? rtrim($prefix, '/') . '/' . $relPath : $relPath;
        if ($file->isDir()) {
            $zip->addEmptyDir($entryName);
        } elseif ($file->isFile()) {
            $zip->addFile($filePath, $entryName);
            $count++;
        }
    }
    return $count;
}

// 1. Add backend (excluding vendor, logs, caches, .sql.enc)
echo "1. Adding backend application files...\n";
$bCount = addFolderToZip($zip, $releaseDir . '/backend', 'backend', [
    '/^vendor\//',
    '/\.log$/',
    '/\.sql\.enc$/',
    '/bootstrap\/cache\/.*\.php$/',
]);

// 2. Add public_html (excluding zips)
echo "2. Adding public_html assets...\n";
$pCount = addFolderToZip($zip, $releaseDir . '/public_html', 'public_html', [
    '/\.zip$/',
    '/\.tar$/',
]);

$zip->close();
$sizeMb = round(filesize($zipPath) / 1024 / 1024, 2);
echo "\nSUCCESS: Created universal update.zip ($sizeMb MB, " . ($bCount + $pCount) . " files)!\n";
