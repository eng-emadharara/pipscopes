<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];

    protected const CACHE_KEY = 'settings.all';

    /** @return array<string, mixed> */
    public static function allValues(): array
    {
        return Cache::rememberForever(static::CACHE_KEY, fn () => static::pluck('value', 'key')->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    /** @param  array<string, mixed>  $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(static::CACHE_KEY);
    }
}
