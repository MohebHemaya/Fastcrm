# Scenario A: Import Robustness Verification
This script tests:
1. Messy CSV with Arabic-Indic, noise, spaces, dashes, international, invalid numbers, merged duplicates across multiple groups.
2. Missing header CSV.
3. Wrong extension and malicious files.
4. Oversized file (> 5MB).
5. Existing ACTIVE driver preservation.
6. Re-import idempotency.
