<?php
// AUDIT helper: parse and compare junit XML files from test runs to check counts and detect flakiness.

function parseJunit(string $path): array {
    if (!file_exists($path) || filesize($path) === 0) {
        return ['error' => "File not found or empty: $path"];
    }
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        return ['error' => "Cannot parse XML: $path"];
    }

    $tests = [];
    $totalTests = 0;
    $totalAssertions = 0;
    $totalFailures = 0;
    $totalErrors = 0;
    $totalTime = 0.0;

    // JUnit XML can have testsuites root or testsuite root
    $suites = $xml->xpath('//testsuite');
    foreach ($suites as $suite) {
        $totalTests += (int)$suite['tests'];
        $totalAssertions += (int)($suite['assertions'] ?? 0);
        $totalFailures += (int)($suite['failures'] ?? 0);
        $totalErrors += (int)($suite['errors'] ?? 0);
        $totalTime += (float)($suite['time'] ?? 0);

        foreach ($suite->testcase as $tc) {
            $class = (string)$tc['class'];
            $name = (string)$tc['name'];
            $time = (float)$tc['time'];
            $status = 'pass';
            if (isset($tc->failure)) {
                $status = 'fail';
            } elseif (isset($tc->error)) {
                $status = 'error';
            } elseif (isset($tc->skipped)) {
                $status = 'skipped';
            }
            $tests["$class::$name"] = [
                'class' => $class,
                'name' => $name,
                'time' => $time,
                'status' => $status,
            ];
        }
    }

    return [
        'file' => basename($path),
        'tests_count' => count($tests),
        'total_tests_attr' => $totalTests,
        'assertions' => $totalAssertions,
        'failures' => $totalFailures,
        'errors' => $totalErrors,
        'time' => $totalTime,
        'tests' => $tests,
    ];
}

$rawDir = __DIR__ . '/../docs/audit/raw';

echo "========================================================\n";
echo "JUNIT TEST RUN COMPARISON & FLAKINESS REPORT\n";
echo "========================================================\n\n";

// 1. Separate Feature & Unit
$unit = parseJunit("$rawDir/junit-unit.xml");
$feature = parseJunit("$rawDir/junit-feature.xml");

if (!isset($unit['error'])) {
    echo sprintf("Unit Suite:    %d tests, %d assertions, %d failures, %.2fs\n",
        $unit['tests_count'], $unit['assertions'], $unit['failures'], $unit['time']);
} else {
    echo "Unit Suite: " . $unit['error'] . "\n";
}

if (!isset($feature['error'])) {
    echo sprintf("Feature Suite: %d tests, %d assertions, %d failures, %.2fs\n",
        $feature['tests_count'], $feature['assertions'], $feature['failures'], $feature['time']);
} else {
    echo "Feature Suite: " . $feature['error'] . "\n";
}

echo "\nCombined Runs (Flakiness Check across 3 full suite runs):\n";
$runs = [];
for ($i = 1; $i <= 3; $i++) {
    $path = "$rawDir/junit-combined-run$i.xml";
    if (file_exists($path)) {
        $res = parseJunit($path);
        if (!isset($res['error'])) {
            $runs[$i] = $res;
            echo sprintf("  Run %d: %d tests, %d assertions, %d failures, %.2fs\n",
                $i, $res['tests_count'], $res['assertions'], $res['failures'], $res['time']);
        } else {
            echo "  Run $i: (running / not yet complete)\n";
        }
    } else {
        echo "  Run $i: (not yet complete)\n";
    }
}

// Compare test results across runs if at least 2 are present
if (count($runs) >= 2) {
    echo "\nComparing results across runs:\n";
    $allTestKeys = [];
    foreach ($runs as $r) {
        $allTestKeys = array_merge($allTestKeys, array_keys($r['tests']));
    }
    $allTestKeys = array_unique($allTestKeys);

    $flaky = [];
    foreach ($allTestKeys as $key) {
        $statuses = [];
        foreach ($runs as $idx => $r) {
            $statuses[$idx] = $r['tests'][$key]['status'] ?? 'missing';
        }
        $uniqueStatuses = array_unique(array_values($statuses));
        if (count($uniqueStatuses) > 1 || in_array('fail', $uniqueStatuses) || in_array('error', $uniqueStatuses)) {
            $flaky[$key] = $statuses;
        }
    }

    if (empty($flaky)) {
        echo "  PASSED: 100% deterministic! Zero flaky tests, zero failures across all completed runs.\n";
    } else {
        echo "  WARNING: Found discrepancies/failures:\n";
        foreach ($flaky as $key => $statuses) {
            echo "    $key: " . json_encode($statuses) . "\n";
        }
    }
}

// Concurrency runs
echo "\nConcurrency Benchmark Runs (20 repeats each):\n";
foreach ([
    'junit-race-accept.xml' => '30 Concurrent Accepts (10+5 order)',
    'junit-race-promote.xml' => 'Concurrent Promote into 1 Slot',
    'junit-webhook-smoke.xml' => '100 Concurrent Webhooks Smoke'
] as $file => $label) {
    $p = "$rawDir/$file";
    if (file_exists($p)) {
        $data = parseJunit($p);
        echo sprintf("  %-35s: %d runs, %d failures, %d errors, %.2fs\n",
            $label, $data['tests_count'], $data['failures'], $data['errors'], $data['time']);
    } else {
        echo "  %-35s: (not yet complete)\n";
    }
}
