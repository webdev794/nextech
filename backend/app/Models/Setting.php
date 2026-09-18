<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value store for runtime configuration an administrator can change
 * without a redeploy. Each value is wrapped as ['v' => ...] so scalars survive
 * the JSON cast, and reads are cached forever until the key is written.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::cacheKey($key),
            fn () => static::query()->find($key)?->value ?? ['missing' => true],
        );

        return array_key_exists('missing', $value) ? $default : $value['v'];
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
        Cache::forget(self::cacheKey($key));
    }

    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }
}
