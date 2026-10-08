# AUDIT static checks (no DB writes). Outputs to docs/audit/raw/static-*.txt
$ErrorActionPreference = "Continue"
$root = "D:\Projects\Web\fast crm"
$raw = "$root\docs\audit\raw"
$php = "C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
function Grep($name, $paths, $pattern, $include = "*") {
  $files = Get-ChildItem -Path $paths -Recurse -File -Include $include -ErrorAction SilentlyContinue |
    Where-Object { $_.FullName -notmatch '\\(node_modules|vendor|\.git|dist|release|docs\\audit|storage\\framework)\\' }
  $files | Select-String -Pattern $pattern | ForEach-Object { "$($_.Path.Replace($root+'\','')):$($_.LineNumber): $($_.Line.Trim())" } |
    Out-File -Encoding utf8 "$raw\$name"
}
# Skipped/disabled tests
Grep "static-skipped-tests.txt" @("$root\backend\tests","$root\frontend\src","$root\frontend\e2e") 'markTestSkipped|->skip\(|->todo\(|test\.skip|it\.skip|describe\.skip|\bxit\(|xdescribe|test\.fixme|->only\(|test\.only|it\.only' @("*.php","*.ts","*.tsx")
# env() outside config
Grep "static-env-outside-config.txt" @("$root\backend\app","$root\backend\routes","$root\backend\database","$root\backend\bootstrap","$root\backend\resources") '\benv\(' @("*.php")
# RTL physical classes
Grep "static-rtl-physical.txt" @("$root\frontend\src") '(?<![\w-])(ml|mr|pl|pr)-[\w\[\]\.\/]+|(?<![\w-])-?(left|right)-[\w\[\]\.\/]+|text-(left|right)\b|float-(left|right)|rounded-[lr](-|\b)|rounded-(tl|tr|bl|br)\b|border-[lr](-|\b)|space-x-|divide-x' @("*.tsx","*.ts","*.css")
# direct axios/fetch outside services/lib
Grep "static-axios-in-components.txt" @("$root\frontend\src\routes","$root\frontend\src\components","$root\frontend\src\features") "from 'axios'|axios\.|\bfetch\(|\bapi\.(get|post|put|patch|delete)\(" @("*.tsx","*.ts")
# any / ts-ignore / console / debugger
Grep "static-ts-any.txt" @("$root\frontend\src") ':\s*any\b|as any\b|<any>|@ts-ignore|@ts-expect-error|eslint-disable' @("*.tsx","*.ts")
Grep "static-console.txt" @("$root\frontend\src") 'console\.(log|debug|warn|error)|\bdebugger\b' @("*.tsx","*.ts")
# hardcoded Arabic outside i18n (frontend source, excluding tests and ar.ts)
Get-ChildItem "$root\frontend\src" -Recurse -File -Include *.tsx,*.ts | Where-Object { $_.FullName -notmatch '\\(test|i18n)\\' } |
  Select-String -Pattern '[\u0600-\u06FF]{2,}' | ForEach-Object { "$($_.Path.Replace($root+'\','')):$($_.LineNumber): $($_.Line.Trim())" } |
  Out-File -Encoding utf8 "$raw\static-hardcoded-arabic.txt"
# English user-facing strings in JSX text nodes / placeholders / aria (heuristic)
Get-ChildItem "$root\frontend\src" -Recurse -File -Include *.tsx | Where-Object { $_.FullName -notmatch '\\test\\' } |
  Select-String -Pattern '>\s*[A-Z][a-z]+( [a-z]+)+\s*<|placeholder="[A-Za-z][^"]*"|aria-label="[A-Za-z][^"]*"|title="[A-Za-z][^"]*"' |
  ForEach-Object { "$($_.Path.Replace($root+'\','')):$($_.LineNumber): $($_.Line.Trim())" } | Out-File -Encoding utf8 "$raw\static-english-ui.txt"
# Secrets in tree
Grep "static-secrets.txt" @("$root\backend","$root\frontend","$root\scripts","$root\docs","$root\.github") '(?i)(api[_-]?key|secret|token|password)\s*[:=]\s*["'']?[A-Za-z0-9_\-\.]{12,}|base64:[A-Za-z0-9+/=]{30,}|EAA[A-Za-z0-9]{20,}|sk_live|AKIA[0-9A-Z]{16}' @("*.php","*.ts","*.tsx","*.js","*.json","*.md","*.yml","*.example","*.env*","*.sh","*.ps1")
# Graph API version hardcoding
Grep "static-graph-version.txt" @("$root\backend\app","$root\backend\config") 'graph\.facebook\.com|v\d{2}\.0' @("*.php")
# Raw phone logging
Grep "static-log-calls.txt" @("$root\backend\app") 'Log::(info|warning|error|debug|notice)\(' @("*.php")
# .env.example contents
Get-Content "$root\backend\.env.example" | Out-File -Encoding utf8 "$raw\static-env-example.txt"
# env vars used in config vs .env.example
$cfgVars = Get-ChildItem "$root\backend\config" -File | Select-String -Pattern "env\('([A-Z0-9_]+)'" -AllMatches | ForEach-Object { $_.Matches } | ForEach-Object { $_.Groups[1].Value } | Sort-Object -Unique
$exVars = Get-Content "$root\backend\.env.example" | Where-Object { $_ -match '^\s*#?\s*([A-Z0-9_]+)=' } | ForEach-Object { ($_ -replace '^\s*#?\s*','').Split('=')[0] } | Sort-Object -Unique
$appVars = Get-ChildItem "$root\backend\app","$root\backend\database" -Recurse -File -Include *.php | Select-String -Pattern "env\('([A-Z0-9_]+)'" -AllMatches | ForEach-Object { $_.Matches } | ForEach-Object { $_.Groups[1].Value } | Sort-Object -Unique
$docText = (Get-ChildItem "$root\docs" -File -Filter *.md | Get-Content -Raw) -join "`n"
& {
  "== env vars read in config/ (count $($cfgVars.Count)) missing from .env.example:"
  $cfgVars | Where-Object { $exVars -notcontains $_ }
  "== env vars read via env() in app/ or database/ (outside config):"
  $appVars
  "== project-specific config env vars (WHATSAPP_*, ADMIN_*, BACKUP_*, ALERT_*, RETENTION_*, SANCTUM_*, SESSION_*) not mentioned anywhere in docs/:"
  ($cfgVars + $appVars) | Sort-Object -Unique | Where-Object { $_ -match '^(WHATSAPP|ADMIN|BACKUP|ALERT|RETENTION|SANCTUM|SESSION|EXPORT|QUESTIONNAIRE|ORDER|HEALTH|MAIL_ALERT)' -and $docText -notmatch [regex]::Escape($_) }
} | Out-File -Encoding utf8 "$raw\static-env-vars.txt"
# Route list
Set-Location "$root\backend"
& $php artisan route:list --json 2>&1 | Out-File -Encoding utf8 "$raw\static-routes.json"
& $php artisan schedule:list 2>&1 | Out-File -Encoding utf8 "$raw\static-schedule-list.txt"
& $php artisan list --raw 2>&1 | Out-File -Encoding utf8 "$raw\static-artisan-list.txt"
"STATIC DONE"
