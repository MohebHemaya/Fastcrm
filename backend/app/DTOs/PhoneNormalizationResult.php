<?php

namespace App\DTOs;

class PhoneNormalizationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?string $e164 = null,
        public readonly ?string $error = null,
        public readonly ?string $countryCode = null
    ) {}

    public static function valid(string $e164, string $countryCode = 'EG'): self
    {
        return new self(
            isValid: true,
            e164: $e164,
            error: null,
            countryCode: $countryCode
        );
    }

    public static function invalid(string $error): self
    {
        return new self(
            isValid: false,
            e164: null,
            error: $error,
            countryCode: null
        );
    }
}
