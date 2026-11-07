<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/* Pengaturan & parameter bisnis global */
class Setting extends Model
{
    public const TYPE_STRING = 'STRING';
    public const TYPE_NUMBER = 'NUMBER';
    public const TYPE_BOOLEAN = 'BOOLEAN';
    public const TYPE_JSON = 'JSON';

    protected $fillable = ['key', 'value', 'type', 'description', 'updated_by'];

    /* Ambil nilai pengaturan dengan fallback */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

    return match ($setting->type) {
    self::TYPE_NUMBER => (int) $setting->value,
    self::TYPE_BOOLEAN => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
    self::TYPE_JSON => json_decode($setting->value, true),
    default => $setting->value,
    };
    }

    public static function set(string $key, mixed $value, string $type = self::TYPE_STRING): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value, 'type' => $type]
        );
    }
}
