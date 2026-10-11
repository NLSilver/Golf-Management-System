<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public const CATEGORY_BAY_TIME = 'bay_time';

    public const CATEGORY_CLUB_RENTAL = 'club_rental';

    public const CATEGORY_LESSON = 'lesson';

    public const CATEGORY_FOOD = 'food';

    protected $fillable = [
        'code',
        'name',
        'category',
        'unit',
        'rate',
        'minimum_minutes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'minimum_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /** The default facility-wide bay rate (1h30m minimum enforced on allocation). */
    public static function bayTime(): ?static
    {
        return static::query()->active()->category(self::CATEGORY_BAY_TIME)->first();
    }
}
