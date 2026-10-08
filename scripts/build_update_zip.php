<?php
$zip = new ZipArchive();
$zipPath = __DIR__ . '/../release/update.zip';

if (file_exists($zipPath)) {
    unlink($zipPath);
}

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $base = dirname(__DIR__);
    $files = [
        'backend/app/Http/Controllers/Api/ImportController.php' => $base . '/release/backend/app/Http/Controllers/Api/ImportController.php',
        'backend/app/Http/Controllers/Api/SidebarBadgeController.php' => $base . '/release/backend/app/Http/Controllers/Api/SidebarBadgeController.php',
        'backend/app/Http/Controllers/Api/AdminHealthController.php' => $base . '/release/backend/app/Http/Controllers/Api/AdminHealthController.php',
        'backend/routes/api.php' => $base . '/release/backend/routes/api.php',
        'public_html/index.html' => $base . '/release/public_html/index.html',
        'public_html/updater.php' => $base . '/release/public_html/updater.php',
        'public_html/assets/index-CZp1yKvy.js' => $base . '/release/public_html/assets/index-CZp1yKvy.js',
        'public_html/assets/index-DK0nhene.css' => $base . '/release/public_html/assets/index-DK0nhene.css',
        'public_html/assets/dist-DKjr5tBM.js' => $base . '/release/public_html/assets/dist-DKjr5tBM.js',
    ];

    foreach ($files as $entryName => $realPath) {
        if (!file_exists($realPath)) {
            die("ERROR: Missing file $realPath\n");
        }
        $zip->addFile($realPath, $entryName);
    }

    $zip->close();
    echo "SUCCESS: Recreated update.zip with POSIX forward slashes! Total files: " . count($files) . "\n";
} else {
    die("ERROR: Could not open $zipPath for writing.\n");
}
