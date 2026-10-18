@echo off
cd /d "%~dp0backend"
"C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -d memory_limit=256M artisan serve
