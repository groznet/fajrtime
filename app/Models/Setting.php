<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings'));
        static::deleted(fn () => Cache::forget('settings'));
    }

    /**
     * Admin override from the settings table, falling back to config/fajr.php 'defaults'.
     */
    public static function get(string $key): mixed
    {
        $overrides = Cache::rememberForever('settings', fn () => static::pluck('value', 'key')->all());

        return $overrides[$key] ?? config("fajr.defaults.$key");
    }

    public static function int(string $key): int
    {
        return (int) static::get($key);
    }
}
