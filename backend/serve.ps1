$php83 = "C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
if (-not (Test-Path $php83)) {
    Write-Error "PHP 8.3 not found at $php83"
    exit 1
}
& $php83 artisan serve
