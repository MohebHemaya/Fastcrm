# AUDIT Concurrency Benchmark Runner (No invalid --repeat flag, tests contain internal 20-run loops)
$root = "D:\Projects\Web\fast crm"
$raw = "$root\docs\audit\raw"
$php = "C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$be = "$root\backend"

Set-Location $be

Write-Host "Running 30 Concurrent Accepts Benchmark (20 internal runs)..."
$t0 = Get-Date
& $php vendor/bin/pest tests/Feature/WorkOrderRaceConditionTest.php --log-junit "$raw\junit-race-accept.xml" 2>&1 | Out-File -Encoding utf8 "$raw\s2-race-accept-benchmark.txt"
$sec = [math]::Round(((Get-Date) - $t0).TotalSeconds, 1)
"s2-race-accept-benchmark.txt`texit=$LASTEXITCODE`tseconds=$sec" | Out-File -Append -Encoding utf8 "$raw\s2-summary.tsv"

Write-Host "Running Concurrent Promote & Cancel Benchmark (20 internal runs)..."
$t0 = Get-Date
& $php vendor/bin/pest tests/Feature/WorkOrderConcurrencyPromoteAndCancelTest.php --log-junit "$raw\junit-race-promote.xml" 2>&1 | Out-File -Encoding utf8 "$raw\s2-race-promote-benchmark.txt"
$sec = [math]::Round(((Get-Date) - $t0).TotalSeconds, 1)
"s2-race-promote-benchmark.txt`texit=$LASTEXITCODE`tseconds=$sec" | Out-File -Append -Encoding utf8 "$raw\s2-summary.tsv"

Write-Host "Running 100-Webhook Smoke Benchmark..."
$t0 = Get-Date
& $php vendor/bin/pest tests/Feature/ConcurrentWebhookSmokeTest.php --log-junit "$raw\junit-webhook-smoke.xml" 2>&1 | Out-File -Encoding utf8 "$raw\s2-webhook-smoke-benchmark.txt"
$sec = [math]::Round(((Get-Date) - $t0).TotalSeconds, 1)
"s2-webhook-smoke-benchmark.txt`texit=$LASTEXITCODE`tseconds=$sec" | Out-File -Append -Encoding utf8 "$raw\s2-summary.tsv"

Write-Host "CONCURRENCY BENCHMARKS COMPLETED"
