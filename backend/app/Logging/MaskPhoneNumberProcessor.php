<?php

namespace App\Logging;

use App\Services\PhoneNormalizer;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class MaskPhoneNumberProcessor implements ProcessorInterface
{
    /**
     * Process log record and mask any sensitive phone numbers.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $this->maskText((string) $record->message);
        $context = $this->maskArray($record->context);

        return $record->with(message: $message, context: $context);
    }

    /**
     * Mask phone numbers in a string.
     */
    public function maskText(string $text): string
    {
        return (string) preg_replace_callback(
            '/(?:\+20\d{9,10}|(?<!\d)01[0125]\d{8}(?!\d)|\+[1-9]\d{7,14})/u',
            fn ($matches) => PhoneNormalizer::mask($matches[0]),
            $text
        );
    }

    /**
     * Recursively mask phone numbers in array context.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    public function maskArray(array $array): array
    {
        $result = [];
        $phoneKeys = ['phone', 'to', 'from', 'recipient', 'mobile', 'driver_phone'];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->maskArray($value);
            } elseif (is_string($value)) {
                if (in_array(strtolower((string) $key), $phoneKeys, true)) {
                    $result[$key] = PhoneNormalizer::mask($value);
                } else {
                    $result[$key] = $this->maskText($value);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
