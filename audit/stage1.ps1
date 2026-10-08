# AUDIT Stage 1 - baseline & environment. Writes only to docs/audit/raw and a throwaway DB driver_system_audit.
$ErrorActionPreference = "Continue"
$root = "D:\Projects\Web\fast crm"
$raw = "$root\docs\audit\raw"
New-Item -ItemType Directory -Force -Path $raw | Out-Null
$php = "C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$mysql = "C:\xampp\mysql\bin\mysql.exe"
function Out($name, [scriptblock]$sb) { & $sb 2>&1 | Out-File -Encoding utf8 "$raw\$name" }

Out "s1-versions.txt" {
  "== php -v"; & $php -v
  "== php -m"; & $php -m
  "== composer -V"; & $php "C:\ProgramData\ComposerSetup\bin\composer.phar" -V 2>$null; composer -V
  "== node -v"; node -v
  "== npm -v"; npm -v
  "== DB version"; & $mysql -u root -e "SELECT VERSION();"
  "== laravel"; Set-Location "$root\backend"; & $php artisan --version
  "== composer show (direct)"; composer show --direct
  "== npm ls (top-level)"; Set-Location "$root\frontend"; npm ls --depth=0
}

Out "s1-git.txt" {
  Set-Location $root
  "== git status"; git status --short
  "== HEAD"; git rev-parse HEAD
  "== branches"; git branch -a
  "== tags"; git tag
  "== log"; git log --oneline -n 10
}

Out "s1-sqlite-grep.txt" {
  Set-Location $root
  Get-ChildItem -Recurse -File -Exclude *.lock,*.map | Where-Object { $_.FullName -notmatch '\\(node_modules|vendor|\.git|release|dist|docs\\audit)\\' } |
    Select-String -Pattern 'sqlite' -SimpleMatch | ForEach-Object { "$($_.Path):$($_.LineNumber): $($_.Line.Trim())" }
}

Out "s1-migrations.txt" {
  & $mysql -u root -e "DROP DATABASE IF EXISTS driver_system_audit; CREATE DATABASE driver_system_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  Set-Location "$root\backend"
  $env:DB_DATABASE = "driver_system_audit"; $env:APP_ENV = "local"
  "== migrate:fresh --seed"; & $php artisan migrate:fresh --seed --force; "EXIT=$LASTEXITCODE"
  "== migrate:rollback (full)"; & $php artisan migrate:reset --force; "EXIT=$LASTEXITCODE"
  "== tables after reset"; & $mysql -u root -N -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='driver_system_audit';"
  "== migrate again"; & $php artisan migrate --force; "EXIT=$LASTEXITCODE"
  "== seed again"; & $php artisan db:seed --force; "EXIT=$LASTEXITCODE"
  Remove-Item Env:DB_DATABASE; Remove-Item Env:APP_ENV
}

Out "s1-charset.txt" {
  "== tables (engine/collation) NOT InnoDB or NOT utf8mb4_unicode_ci"
  & $mysql -u root -e "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='driver_system_audit' AND (ENGINE<>'InnoDB' OR TABLE_COLLATION<>'utf8mb4_unicode_ci');"
  "== columns with non-utf8mb4 charset/collation"
  & $mysql -u root -e "SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='driver_system_audit' AND CHARACTER_SET_NAME IS NOT NULL AND (CHARACTER_SET_NAME<>'utf8mb4' OR COLLATION_NAME<>'utf8mb4_unicode_ci');"
  "== all tables"
  & $mysql -u root -e "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='driver_system_audit' ORDER BY TABLE_NAME;"
  "== schema default"
  & $mysql -u root -e "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('driver_system','driver_system_test','driver_system_audit');"
}
"done"
