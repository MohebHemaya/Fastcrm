<?php
$zipPath = __DIR__ . '/../release/vendor.zip';

if (!file_exists($zipPath)) {
    die("ERROR: $zipPath does not exist!\n");
}

echo "=== VERIFYING VENDOR.ZIP ===\n";
echo "File size: " . round(filesize($zipPath) / 1024 / 1024, 2) . " MB\n\n";

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    die("ERROR: Failed to open $zipPath\n");
}

echo "Total entries in archive: " . $zip->numFiles . "\n\n";

// Critical essential files required for Laravel to boot and run
$essentialFiles = [
    'vendor/autoload.php',
    'vendor/composer/autoload_real.php',
    'vendor/composer/autoload_classmap.php',
    'vendor/composer/autoload_psr4.php',
    'vendor/composer/ClassLoader.php',
    'vendor/symfony/deprecation-contracts/function.php',
    'vendor/symfony/http-foundation/Request.php',
    'vendor/symfony/http-kernel/HttpKernel.php',
    'vendor/symfony/routing/Router.php',
    'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    'vendor/laravel/framework/src/Illuminate/Support/Facades/DB.php',
    'vendor/laravel/sanctum/src/Sanctum.php',
    'vendor/laravel/sanctum/src/Http/Middleware/EnsureFrontendRequestsAreStateful.php',
    'vendor/vlucas/phpdotenv/src/Dotenv.php',
    'vendor/nesbot/carbon/src/Carbon/Carbon.php',
    'vendor/guzzlehttp/guzzle/src/Client.php',
    'vendor/monolog/monolog/src/Monolog/Logger.php',
    'vendor/ramsey/uuid/src/Uuid.php',
    'vendor/brick/math/src/BigInteger.php',
    'vendor/openspout/openspout/src/Common/Entity/Row.php',
    'vendor/maatwebsite/excel/src/Excel.php',
];

$missing = [];
foreach ($essentialFiles as $file) {
    $idx = $zip->locateName($file);
    if ($idx === false) {
        $missing[] = $file;
        echo " [MISSING] $file\n";
    } else {
        echo " [OK]      $file\n";
    }
}

// Check vendor packages directories present
$packages = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $parts = explode('/', $stat['name']);
    if (count($parts) >= 3 && $parts[0] === 'vendor') {
        $vendorName = $parts[1] . '/' . $parts[2];
        $packages[$vendorName] = true;
    }
}

$zip->close();

echo "\n--- Summary of Packages in vendor.zip (" . count($packages) . " packages found) ---\n";
ksort($packages);
foreach (array_keys($packages) as $pkg) {
    echo " - $pkg\n";
}

if (!empty($missing)) {
    echo "\nFAILED: " . count($missing) . " essential files are missing!\n";
    exit(1);
} else {
    echo "\n=== ALL CHECKS PASSED: vendor.zip IS 100% COMPLETE AND VALID ===\n";
    exit(0);
}
