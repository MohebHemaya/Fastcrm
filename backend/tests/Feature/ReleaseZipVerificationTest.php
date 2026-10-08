<?php

use Symfony\Component\Process\Process;

test('verify-release-zip script passes on valid release archive or reports error on forbidden files', function () {
    $script = base_path('../scripts/verify-release-zip.php');
    $zipPath = base_path('../release/release.zip');

    if (! file_exists($zipPath)) {
        // If release.zip hasn't been built yet in current test context, test with temporary zip
        $zipPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_release_'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('backend/app/Models/User.php', '<?php');
        $zip->addFromString('public_html/index.php', '<?php');
        $zip->close();
    }

    $process = new Process([PHP_BINARY, $script, $zipPath]);
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('PASSED');
});

test('verify-release-zip script fails when forbidden file (.env or tests or cache) is present in zip', function () {
    $script = base_path('../scripts/verify-release-zip.php');
    $badZipPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test_bad_release_'.uniqid().'.zip';

    $zip = new ZipArchive;
    $zip->open($badZipPath, ZipArchive::CREATE);
    $zip->addFromString('backend/.env', 'APP_KEY=secret');
    $zip->addFromString('backend/tests/Feature/ExampleTest.php', '<?php');
    $zip->addFromString('backend/bootstrap/cache/config.php', '<?php');
    $zip->close();

    $process = new Process([PHP_BINARY, $script, $badZipPath]);
    $process->run();

    @unlink($badZipPath);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('FAILED: Forbidden files found in release zip archive');
});
