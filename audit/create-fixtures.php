<?php
// AUDIT helper: generate realistic test fixtures for Stage 5 behavior verification.

$fixturesDir = __DIR__ . '/fixtures';
if (!is_dir($fixturesDir)) {
    mkdir($fixturesDir, 0777, true);
}

// 1. Messy CSV with Arabic-Indic, spaces, dashes, international, invalid, duplicates
$messyCsv = "\xEF\xBB\xBF"; // UTF-8 BOM
$messyCsv .= "رقم الهاتف,اسم المجموعة\n";
$messyCsv .= "٠١٠١٢٣٤٥٦٧٨,سائقي الدلتا الشرقية\n"; // Arabic-Indic digits
$messyCsv .= "+20 11 2345 6789,سائقي الجيزة\n"; // spaces
$messyCsv .= "0020-12-3456-7890,سائقي القاهرة الكبرى\n"; // dashes & 0020
$messyCsv .= "01512345678,سائقي الإسكندرية\n"; // standard 015
$messyCsv .= "1012345678,سائقي القليوبية\n"; // 10-digit without leading 0
$messyCsv .= "\n"; // empty row
$messyCsv .= "  01099998888  ,جروب وسط البلد\n"; // leading/trailing spaces
$messyCsv .= "01099998888,جروب شبرا الخيمة\n"; // duplicate across different group!
$messyCsv .= "+966501234567,سائقي المملكة العربية السعودية\n"; // foreign valid number
$messyCsv .= "0223456789,أرقام أرضية بالقاهرة\n"; // landline invalid
$messyCsv .= "010123,رقم قصير جدًا\n"; // too short
$messyCsv .= "010123456789012345,رقم طويل جدًا\n"; // too long
$messyCsv .= "abc01012345678,أحرف أبجدية غير صالحة\n"; // not a number
$messyCsv .= "+201000000001,سائقي المعادي\n"; // test active driver candidate

file_put_contents("$fixturesDir/messy_import.csv", $messyCsv);
echo "Created: $fixturesDir/messy_import.csv\n";

// 2. Messy CSV without header
$noHeaderCsv = "\xEF\xBB\xBF";
$noHeaderCsv .= "01011112222,مجموعة بدون رأس ملف 1\n";
$noHeaderCsv .= "01122223333,مجموعة بدون رأس ملف 2\n";
$noHeaderCsv .= "01233334444,مجموعة بدون رأس ملف 3\n";
file_put_contents("$fixturesDir/no_header.csv", $noHeaderCsv);
echo "Created: $fixturesDir/no_header.csv\n";

// 3. Wrong extension / malicious masquerading
file_put_contents("$fixturesDir/malicious.php", "<?php echo 'malicious code'; phpinfo(); ?>");
file_put_contents("$fixturesDir/fake_excel.php.xlsx", "<?php echo 'fake excel with php'; ?>");
file_put_contents("$fixturesDir/wrong_ext.txt", "Some text file");
echo "Created malicious & wrong-extension fixtures.\n";

// 4. Oversized file (> 5MB)
$oversizedPath = "$fixturesDir/oversized.csv";
$fp = fopen($oversizedPath, 'wb');
fwrite($fp, "\xEF\xBB\xBFرقم الهاتف,اسم المجموعة\n");
$line = str_repeat("01012345678,مجموعة اختبار الأحجام الكبيرة جدًا جدًا جدًا\n", 50);
$lineLen = strlen($line);
$targetBytes = 5.5 * 1024 * 1024; // 5.5MB
$written = 0;
while ($written < $targetBytes) {
    fwrite($fp, $line);
    $written += $lineLen;
}
fclose($fp);
echo "Created oversized file (size: " . round(filesize($oversizedPath) / 1024 / 1024, 2) . " MB)\n";

// 5. 5,000-row CSV file
$fiveKPath = "$fixturesDir/5000_drivers.csv";
$fp = fopen($fiveKPath, 'wb');
fwrite($fp, "\xEF\xBB\xBFرقم الهاتف,اسم المجموعة\n");
for ($i = 1; $i <= 5000; $i++) {
    $phone = sprintf("+2010%08d", $i);
    $grp = "مجموعة الشحن رقم " . ($i % 20 + 1);
    fwrite($fp, "$phone,$grp\n");
}
fclose($fp);
echo "Created 5,000-row file (size: " . round(filesize($fiveKPath) / 1024, 2) . " KB)\n";

echo "ALL FIXTURES GENERATED SUCCESSFULLY!\n";
