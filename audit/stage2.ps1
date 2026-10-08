# AUDIT Stage 2 - automated gates + flakiness + concurrency repeats. Serialized to avoid DB/CPU interference.
$ErrorActionPreference = "Continue"
$root = "D:\Projects\Web\fast crm"
$raw = "$root\docs\audit\raw"
$php = "C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$be = "$root\backend"; $fe = "$root\frontend"
function Run($name, $dir, [scriptblock]$sb) {
  Set-Location $dir
  $t0 = Get-Date
  & $sb 2>&1 | Out-File -Encoding utf8 "$raw\$name"
  $code = $LASTEXITCODE
  $sec = [math]::Round(((Get-Date) - $t0).TotalSeconds, 1)
  "$name`texit=$code`tseconds=$sec" | Out-File -Append -Encoding utf8 "$raw\s2-summary.tsv"
}
Remove-Item "$raw\s2-summary.tsv" -ErrorAction SilentlyContinue

# --- static gates
Run "s2-pint.txt" $be { & $php vendor/bin/pint --test }
Run "s2-phpstan.txt" $be { & $php vendor/bin/phpstan analyse --memory-limit=1G --no-progress }
Run "s2-baseline.txt" $root { & $php scripts/check-baseline-size.php }
Run "s2-tsc.txt" $fe { npx tsc -b }
Run "s2-lint.txt" $fe { npm run lint }
Run "s2-vitest.txt" $fe { npx vitest run }
Run "s2-build.txt" $fe { npm run build }

# --- backend suites (separate, then 3 combined runs for flakiness)
Run "s2-pest-unit.txt" $be { & $php vendor/bin/pest --testsuite=Unit --log-junit "$raw\junit-unit.xml" }
Run "s2-pest-feature.txt" $be { & $php vendor/bin/pest --testsuite=Feature --log-junit "$raw\junit-feature.xml" }
foreach ($i in 1..3) {
  Run "s2-pest-combined-run$i.txt" $be { & $php artisan test --log-junit "$raw\junit-combined-run$i.xml" }
}

# --- concurrency repeats (20x each file)
Run "s2-race-accept-x20.txt" $be { & $php vendor/bin/pest tests/Feature/WorkOrderRaceConditionTest.php --repeat=20 --log-junit "$raw\junit-race-accept.xml" }
Run "s2-race-promote-x20.txt" $be { & $php vendor/bin/pest tests/Feature/WorkOrderConcurrencyPromoteAndCancelTest.php --repeat=20 --log-junit "$raw\junit-race-promote.xml" }
Run "s2-webhook-smoke-x20.txt" $be { & $php vendor/bin/pest tests/Feature/ConcurrentWebhookSmokeTest.php --repeat=20 --log-junit "$raw\junit-webhook-smoke.xml" }

# --- E2E twice
foreach ($i in 1..2) {
  Run "s2-e2e-run$i.txt" $fe { npx playwright test --reporter=list }
}
"STAGE2 DONE" | Out-File -Append -Encoding utf8 "$raw\s2-summary.tsv"
