$ErrorActionPreference = "Stop"
$Root = "d:\Projects\Web\fast crm"
$Rel = Join-Path $Root "release"
$Src = Join-Path $Root "backend"
$Dst = Join-Path $Rel "backend"

# Preserve the production .env files
$envTxt = Get-Content (Join-Path $Dst ".env") -Raw -Encoding utf8
$envProdTxt = Get-Content (Join-Path $Dst ".env.production") -Raw -Encoding utf8

# Wipe old (nested/polluted) backend copy
cmd /c rmdir /s /q "$Dst"
New-Item -ItemType Directory -Path $Dst | Out-Null

# Clean copy with robocopy (no nesting, excludes tests/logs/caches)
foreach ($d in @("app","bootstrap","config","database","lang","routes","resources","storage","vendor")) {
    if (Test-Path (Join-Path $Src $d)) {
        robocopy (Join-Path $Src $d) (Join-Path $Dst $d) /E /NFL /NDL /NJH /NJS /NP /XF "*.log" | Out-Null
    }
}
foreach ($f in @("artisan","composer.json","composer.lock")) {
    Copy-Item (Join-Path $Src $f) (Join-Path $Dst $f) -Force
}

# Remove compiled caches & logs & sessions
Get-ChildItem (Join-Path $Dst "bootstrap\cache") -Filter "*.php" -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem (Join-Path $Dst "storage\logs") -File -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Force
Get-ChildItem (Join-Path $Dst "storage\framework\views") -File -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Force
Get-ChildItem (Join-Path $Dst "storage\framework\sessions") -File -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Force
Get-ChildItem (Join-Path $Dst "storage\framework\cache") -Recurse -File -ErrorAction SilentlyContinue | Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Force

foreach ($d in @("storage\app\exports","storage\app\backups","storage\framework\cache\data","storage\framework\sessions","storage\framework\views","storage\logs","bootstrap\cache")) {
    New-Item -ItemType Directory -Path (Join-Path $Dst $d) -Force | Out-Null
}

# Restore .env files (UTF-8 no BOM)
$utf8 = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText((Join-Path $Dst ".env"), $envTxt, $utf8)
[System.IO.File]::WriteAllText((Join-Path $Dst ".env.production"), $envProdTxt, $utf8)

# Sanity
$bad = Get-ChildItem $Dst -Directory | ForEach-Object { Test-Path (Join-Path $_.FullName $_.Name) } | Where-Object { $_ }
Write-Host "Nested duplicates found: $($bad.Count)"
Write-Host "bootstrap/app.php exists: $(Test-Path (Join-Path $Dst 'bootstrap\app.php'))"

# Zip
Set-Location $Rel
Remove-Item backend.zip, full_release.zip -Force -ErrorAction SilentlyContinue
tar.exe -a -c -f backend.zip backend
tar.exe -a -c -f full_release.zip backend public_html database.sql
Get-ChildItem *.zip | Select-Object Name, Length
