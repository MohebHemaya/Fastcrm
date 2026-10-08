$b = "d:\Projects\Web\fast crm\release\backend"
foreach ($d in "storage\app\exports","storage\app\backups","storage\framework\cache\data","storage\framework\sessions","storage\framework\views","storage\logs","bootstrap\cache") {
    $p = Join-Path $b $d
    New-Item -ItemType Directory -Path $p -Force | Out-Null
    if (-not (Get-ChildItem $p -Force)) { Set-Content (Join-Path $p ".gitkeep") "" }
}
"TOP LEVEL:"; Get-ChildItem $b -Force | ForEach-Object { $_.Name }
"BOOTSTRAP:"; Get-ChildItem "$b\bootstrap" -Force | ForEach-Object { $_.Name }
"FILE COUNT: " + (Get-ChildItem $b -Recurse -File -Force | Measure-Object).Count
"LOGS/CACHE FILES:"; Get-ChildItem "$b\storage\logs","$b\bootstrap\cache" -Force | ForEach-Object { $_.Name }
