<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Retrieve a setting value by key, or return the default value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting ? ($setting->value ?? $default) : $default;
    }

    /**
     * Store or update a setting value by key.
     */
    public static function set(string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
