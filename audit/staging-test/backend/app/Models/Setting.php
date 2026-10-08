<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string|null $description_ar
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description_ar',
    ];

    /**
     * Cache array for loaded settings.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $settingsCache = null;

    /**
     * Get a setting value with typed casting and fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::find($key);

        if (! $setting || $setting->value === null) {
            return $default;
        }

        return match ($setting->type) {
            'integer', 'int' => (int) $setting->value,
            'float', 'double', 'decimal' => (float) $setting->value,
            'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($setting->value, true) ?? $default,
            default => (string) $setting->value,
        };
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $description = null): Setting
    {
        $type = $type ?? (match (gettype($value)) {
            'integer' => 'integer',
            'boolean' => 'boolean',
            'double' => 'float',
            'array' => 'json',
            default => 'string',
        });

        $storedValue = match ($type) {
            'boolean', 'bool' => $value ? '1' : '0',
            'json', 'array' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        $data = [
            'value' => $storedValue,
            'type' => $type,
        ];

        if ($description !== null) {
            $data['description_ar'] = $description;
        }

        return static::updateOrCreate(['key' => $key], $data);
    }
}
