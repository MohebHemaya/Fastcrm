<?php
ini_set('memory_limit', '1024M');
set_time_limit(600);

echo "=== BUILDING PRODUCTION RELEASE ZIPS ===\n\n";

$releaseDir = dirname(__DIR__) . '/release';

// -------------------------------------------------------------
// Helper function to zip a folder recursively with forward slashes
// -------------------------------------------------------------
function zipFolder($sourceDir, $zipPath, $prefix = '', $excludePatterns = []) {
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Cannot open $zipPath\n");
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $sourceDir = rtrim(str_replace('\\', '/', realpath($sourceDir)), '/');
    $count = 0;
    
    foreach ($files as $file) {
        $filePath = str_replace('\\', '/', $file->getRealPath());
        $relPath = substr($filePath, strlen($sourceDir) + 1);
        
        // Check excludes
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
    
    $zip->close();
    $sizeMb = round(filesize($zipPath) / 1024 / 1024, 2);
    echo "[OK] Built " . basename($zipPath) . " ($sizeMb MB, $count files)\n";
}

// 1. Build public_html.zip
echo "1. Building public_html.zip ...\n";
zipFolder(
    $releaseDir . '/public_html',
    $releaseDir . '/public_html.zip',
    '', // flat inside public_html
    ['/\.zip$/', '/\.tar$/']
);

// 2. Build backend.zip (Full with vendor) - FLAT inside backend
echo "2. Building backend.zip (Full with vendor) ...\n";
zipFolder(
    $releaseDir . '/backend',
    $releaseDir . '/backend.zip',
    '', // flat inside backend folder
    ['/\.sql\.enc$/', '/\.log$/']
);

// 3. Build backend_app.zip (Fast app-only without vendor) - FLAT inside backend
echo "3. Building backend_app.zip (without vendor) ...\n";
zipFolder(
    $releaseDir . '/backend',
    $releaseDir . '/backend_app.zip',
    '', // flat inside backend folder
    ['/^vendor\//', '/\.sql\.enc$/', '/\.log$/']
);

echo "\n=== ALL RELEASE ZIPS BUILT SUCCESSFULLY ===\n";
