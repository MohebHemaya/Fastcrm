<?php

/**
 * CI Guard: Enforces that phpstan-baseline.neon does not grow beyond its locked size.
 * Prevents developers from sneaking new static analysis errors into the baseline.
 */

$baselinePath = __DIR__ . '/../backend/phpstan-baseline.neon';

if (!file_exists($baselinePath)) {
    fwrite(STDERR, "Error: phpstan-baseline.neon not found at {$baselinePath}\n");
    exit(1);
}

$content = (string) file_get_contents($baselinePath);
$entryCount = substr_count($content, 'message:');

// Sum of counts if multiple occurrences of an error exist
preg_match_all('/count:\s*(\d+)/', $content, $matches);
$totalErrorCount = !empty($matches[1]) ? array_sum(array_map('intval', $matches[1])) : $entryCount;

// Maximum allowed entries and errors (locked at current baseline size)
$maxAllowedEntries = 89;
$maxAllowedErrors = 103;

echo "===========================================\n";
echo "PHPStan Baseline Size Check\n";
echo "===========================================\n";
echo "Current baseline rule entries: {$entryCount} (Max allowed: {$maxAllowedEntries})\n";
echo "Current total baseline errors: {$totalErrorCount} (Max allowed: {$maxAllowedErrors})\n";

if ($entryCount > $maxAllowedEntries || $totalErrorCount > $maxAllowedErrors) {
    fwrite(STDERR, "\nFAILED: phpstan-baseline.neon has grown beyond its locked limits!\n");
    fwrite(STDERR, "Entries: {$entryCount} (max: {$maxAllowedEntries}), Errors: {$totalErrorCount} (max: {$maxAllowedErrors})\n");
    fwrite(STDERR, "Do not add new errors to the baseline. Fix the typing/nullability issues in your code.\n\n");
    exit(1);
}

echo "PASSED: Baseline has not grown beyond current limits.\n";
exit(0);
