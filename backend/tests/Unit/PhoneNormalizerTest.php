<?php

use App\Services\PhoneNormalizer;

test('PhoneNormalizer correctly normalizes and validates phone numbers (table-driven 40+ cases)', function (
    ?string $input,
    bool $expectedValid,
    ?string $expectedE164,
    ?string $expectedError,
    ?string $expectedCountry = null
) {
    $result = PhoneNormalizer::normalize($input);

    expect($result->isValid)->toBe($expectedValid);

    if ($expectedValid) {
        expect($result->e164)->toBe($expectedE164);
        if ($expectedCountry !== null) {
            expect($result->countryCode)->toBe($expectedCountry);
        }
        expect($result->error)->toBeNull();
    } else {
        expect($result->error)->toBe($expectedError);
        expect($result->e164)->toBeNull();
    }
})->with([
    // --- 1. Standard Egyptian 11-digit mobile formats ---
    ['01012345678', true, '+201012345678', null, 'EG'],
    ['01112345678', true, '+201112345678', null, 'EG'],
    ['01212345678', true, '+201212345678', null, 'EG'],
    ['01512345678', true, '+201512345678', null, 'EG'],

    // --- 2. Egyptian 10-digit without leading zero ---
    ['1012345678', true, '+201012345678', null, 'EG'],
    ['1112345678', true, '+201112345678', null, 'EG'],
    ['1212345678', true, '+201212345678', null, 'EG'],
    ['1512345678', true, '+201512345678', null, 'EG'],

    // --- 3. Egyptian with country code (20, +20, 0020) ---
    ['201012345678', true, '+201012345678', null, 'EG'],
    ['+201012345678', true, '+201012345678', null, 'EG'],
    ['00201012345678', true, '+201012345678', null, 'EG'],
    ['201198765432', true, '+201198765432', null, 'EG'],
    ['+201234567890', true, '+201234567890', null, 'EG'],
    ['00201555555555', true, '+201555555555', null, 'EG'],

    // --- 4. Arabic-Indic digits (Eastern Arabic ٠١٢٣٤٥٦٧٨٩) ---
    ['٠١٠١٢٣٤٥٦٧٨', true, '+201012345678', null, 'EG'],
    ['٠١١٩٨٧٦٥٤٣٢', true, '+201198765432', null, 'EG'],
    ['١٢١٢٣٤٥٦٧٨', true, '+201212345678', null, 'EG'],
    ['+٢٠١٥٥٥٥٥٥٥٥٥', true, '+201555555555', null, 'EG'],
    ['٠٠٢٠١٠١٢٣٤٥٦٧٨', true, '+201012345678', null, 'EG'],

    // --- 5. Persian digits (۰۱۲۳۴۵۶۷۸۹) ---
    ['۰۱۰۱۲۳۴۵۶۷۸', true, '+201012345678', null, 'EG'],
    ['۰۱۱۱۲۳۴۵۶۷۸', true, '+201112345678', null, 'EG'],

    // --- 6. Formatting, spaces, punctuation, invisible & RTL marks ---
    ['010-1234-5678', true, '+201012345678', null, 'EG'],
    ['(010) 1234 5678', true, '+201012345678', null, 'EG'],
    ['010.1234.5678', true, '+201012345678', null, 'EG'],
    ['+20 10 1234 5678', true, '+201012345678', null, 'EG'],
    ['+20-10-1234-5678', true, '+201012345678', null, 'EG'],
    [' 01012345678 ', true, '+201012345678', null, 'EG'],
    ['010/1234/5678', true, '+201012345678', null, 'EG'],
    ["010\u{00A0}1234\u{00A0}5678", true, '+201012345678', null, 'EG'], // Non-breaking space
    ["\u{200F}01012345678\u{200E}", true, '+201012345678', null, 'EG'], // RTL / LTR marks
    ["\u{FEFF}01012345678", true, '+201012345678', null, 'EG'], // BOM
    ["010\u{200B}1234\u{200C}5678", true, '+201012345678', null, 'EG'], // Zero-width spaces

    // --- 7. Valid foreign numbers ---
    ['+966512345678', true, '+966512345678', null, 'SA'], // Saudi Arabia
    ['00966512345678', true, '+966512345678', null, 'SA'], // Saudi Arabia (00 prefix)
    ['+971501234567', true, '+971501234567', null, 'AE'], // UAE
    ['+96599123456', true, '+96599123456', null, 'KW'], // Kuwait
    ['+14155552671', true, '+14155552671', null, 'US'], // USA
    ['+447400123456', true, '+447400123456', null, 'GB'], // UK

    // --- 8. Invalid Egyptian numbers (prefix, length, landline) ---
    ['010123456', false, null, 'too_short'],
    ['1012345', false, null, 'too_short'],
    ['0101234567899', false, null, 'too_long'],
    ['01912345678', false, null, 'bad_prefix'],
    ['01412345678', false, null, 'bad_prefix'],
    ['01712345678', false, null, 'bad_prefix'],
    ['0223456789', false, null, 'landline'], // Cairo landline
    ['+20223456789', false, null, 'landline'],
    ['035432100', false, null, 'landline'], // Alexandria landline
    ['0133221100', false, null, 'landline'], // Banha landline (013)

    // --- 9. Empty, non-number, garbage ---
    [null, false, null, 'empty'],
    ['', false, null, 'empty'],
    ['   ', false, null, 'empty'],
    ['abc', false, null, 'not_a_number'],
    ['0101234567a', false, null, 'not_a_number'],
    ['سائق ممتاز', false, null, 'not_a_number'],
    ['+2010abc5678', false, null, 'not_a_number'],
    ['00000', false, null, 'too_short'],
    ['+999123456789', false, null, 'invalid'],
]);
