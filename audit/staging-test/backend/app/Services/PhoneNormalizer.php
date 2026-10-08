<?php

namespace App\Services;

use App\DTOs\PhoneNormalizationResult;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class PhoneNormalizer
{
    /**
     * Map of Eastern Arabic-Indic and Persian digits to ASCII digits.
     */
    protected static array $digitMap = [
        // Arabic-Indic
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        // Persian/Urdu
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * Normalize phone number to E.164.
     */
    public static function normalize(?string $phone, string $defaultRegion = 'EG'): PhoneNormalizationResult
    {
        if ($phone === null) {
            return PhoneNormalizationResult::invalid('empty');
        }

        // 1. Convert Arabic-Indic and Persian digits to ASCII
        $cleaned = strtr($phone, self::$digitMap);

        // 2. Strip formatting, spaces, brackets, invisible chars, and RTL marks
        // \x{200B}-\x{200D}, \x{FEFF} = zero-width chars
        // \x{200E}, \x{200F}, \x{202A}-\x{202E} = RTL/LTR marks
        $cleaned = preg_replace('/[\s\xA0\(\)\-\.\,\/\\\_\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}\x{202A}-\x{202E}]+/u', '', $cleaned);
        $cleaned = trim((string) $cleaned);

        if ($cleaned === '') {
            return PhoneNormalizationResult::invalid('empty');
        }

        // 3. Check for letters or invalid non-digit symbols (except optional single leading +)
        $hasLeadingPlus = str_starts_with($cleaned, '+');
        $digitsOnly = $hasLeadingPlus ? substr($cleaned, 1) : $cleaned;

        if (! ctype_digit($digitsOnly)) {
            return PhoneNormalizationResult::invalid('not_a_number');
        }

        // 4. Handle international prefix '00'
        if (str_starts_with($cleaned, '00')) {
            $cleaned = '+'.substr($cleaned, 2);
            $hasLeadingPlus = true;
            $digitsOnly = substr($cleaned, 1);
        }

        // 5. Special Egyptian fast-path and custom prefix rules
        $isEgyptianCandidate = ! $hasLeadingPlus || str_starts_with($cleaned, '+20');

        if ($isEgyptianCandidate) {
            // Strip leading +20 or 20
            $egDigits = $digitsOnly;
            if (str_starts_with($egDigits, '20') && strlen($egDigits) >= 11) {
                $egDigits = substr($egDigits, 2);
            }

            // If starts with 0, check length and prefix
            if (str_starts_with($egDigits, '0')) {
                // If starts with 01
                if (str_starts_with($egDigits, '01')) {
                    $prefix = substr($egDigits, 0, 3);
                    if (! in_array($prefix, ['010', '011', '012', '015'], true)) {
                        // Could be 013 (Qalyubia/Banha landline) or invalid mobile prefix
                        if ($prefix === '013') {
                            return PhoneNormalizationResult::invalid('landline');
                        }

                        return PhoneNormalizationResult::invalid('bad_prefix');
                    }

                    if (strlen($egDigits) < 11) {
                        return PhoneNormalizationResult::invalid('too_short');
                    }
                    if (strlen($egDigits) > 11) {
                        return PhoneNormalizationResult::invalid('too_long');
                    }

                    return PhoneNormalizationResult::valid('+20'.substr($egDigits, 1), 'EG');
                }

                // Other 0x prefixes in Egypt are landlines (02 Cairo, 03 Alex, 04x, 05x, 08x, 09x)
                if (strlen($egDigits) >= 8 && strlen($egDigits) <= 10) {
                    return PhoneNormalizationResult::invalid('landline');
                }
            }

            // If 10 digits starting with 10, 11, 12, 15 (without leading 0)
            if (strlen($egDigits) === 10) {
                $prefix2 = substr($egDigits, 0, 2);
                if (in_array($prefix2, ['10', '11', '12', '15'], true)) {
                    return PhoneNormalizationResult::valid('+20'.$egDigits, 'EG');
                }
            }

            // If starts with 1 but not valid mobile
            if (str_starts_with($egDigits, '1') && strlen($egDigits) < 10) {
                return PhoneNormalizationResult::invalid('too_short');
            }
            if (str_starts_with($egDigits, '1') && strlen($egDigits) > 10) {
                return PhoneNormalizationResult::invalid('too_long');
            }
        }

        // 6. Use libphonenumber for generic validation (including foreign numbers and other regions)
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $numberToParse = $hasLeadingPlus ? $cleaned : '+'.$cleaned;
            // Try parsing with country code if plus present, otherwise defaultRegion
            $proto = $hasLeadingPlus
                ? $phoneUtil->parse($cleaned, null)
                : $phoneUtil->parse($cleaned, $defaultRegion);

            if (! $phoneUtil->isValidNumber($proto)) {
                // Determine reason if invalid
                $len = strlen($digitsOnly);
                if ($len < 8) {
                    return PhoneNormalizationResult::invalid('too_short');
                }
                if ($len > 15) {
                    return PhoneNormalizationResult::invalid('too_long');
                }

                return PhoneNormalizationResult::invalid('invalid');
            }

            // Check if landline/fixed-line
            $numberType = $phoneUtil->getNumberType($proto);
            if ($numberType === PhoneNumberType::FIXED_LINE) {
                return PhoneNormalizationResult::invalid('landline');
            }

            $e164 = $phoneUtil->format($proto, PhoneNumberFormat::E164);
            $regionCode = $phoneUtil->getRegionCodeForNumber($proto) ?? $defaultRegion;

            return PhoneNormalizationResult::valid($e164, $regionCode);
        } catch (NumberParseException $e) {
            $code = $e->getErrorType();
            $len = strlen($digitsOnly);

            if ($len < 7) {
                return PhoneNormalizationResult::invalid('too_short');
            }
            if ($len > 15) {
                return PhoneNormalizationResult::invalid('too_long');
            }

            return PhoneNormalizationResult::invalid('invalid');
        }
    }

    /**
     * Mask a phone number keeping prefix and the last 3-4 digits.
     * Example: '+201012345678' -> '+201*****5678'
     */
    public static function mask(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        $length = strlen($phone);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $suffixLen = min(4, max(3, $length - 4));
        $prefixLen = str_starts_with($phone, '+') ? 4 : 3;

        if ($length <= $prefixLen + $suffixLen) {
            $prefixLen = 2;
            $suffixLen = min(3, max(1, $length - 3));
        }

        $maskedCount = max(1, $length - $prefixLen - $suffixLen);

        return substr($phone, 0, $prefixLen).str_repeat('*', $maskedCount).substr($phone, -$suffixLen);
    }
}
