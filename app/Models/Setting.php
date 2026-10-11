<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['name', 'value', 'group'];

    /** Read a setting with an optional fallback. */
    public static function getValue(string $name, mixed $default = null): mixed
    {
        $setting = static::query()->where('name', $name)->first();

        return $setting?->value ?? $default;
    }

    public static function setValue(string $name, mixed $value, string $group = 'general'): self
    {
        return static::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value, 'group' => $group],
        );
    }

    public static function minutes(string $name, int $default): int
    {
        return (int) static::getValue($name, $default);
    }
}
