<?php

declare(strict_types=1);

namespace Huythang304\Settings\Supports;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Setting
{
	public static function get(string $key, mixed $default = null): mixed
    {
        [$group, $setting, $subKey] = static::parseKey($key);

        $cacheKey = static::getCacheKey($group, $setting);
        $cacheTtl = config('settings.cache.ttl');

        $callback = fn () => static::fetchSetting($group, $setting);

        // Use remember() with TTL if provided, otherwise rememberForever()
        $data = ($cacheTtl > 0)
            ? Cache::remember($cacheKey, $cacheTtl * 60, $callback)
            : Cache::rememberForever($cacheKey, $callback);

        $value = data_get($data, $subKey, $default);

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        [$group, $setting] = static::parseKey($key);

        $cacheKey = static::getCacheKey($group, $setting);

        Cache::forget($cacheKey);

        static::storeSetting($group, $setting, $value);
    }

    public static function getGroup(string $group): ?array
    {
        $settings = [];

        $tableName = config('settings.table_name', 'settings');

        DB::table($tableName)->where('group', $group)->get()->each(function (\stdClass $setting) use (&$settings) {
            $settings[$setting->key] = json_decode($setting->value, true);
        });

        return $settings;
    }

    public static function getGroupLastUpdatedAt(string $group, string $format = 'F j, Y, g:i a', string $timezone = 'UTC'): ?string
    {
        $tableName = config('settings.table_name', 'settings');

        $timestamp = DB::table($tableName)
            ->where('group', $group)
            ->max('updated_at');

        if (empty($timestamp)) {
            return null;
        }

        try {
            $fromTz = config('app.timezone', 'UTC');
            $dt = $timestamp instanceof Carbon
                ? $timestamp
                : Carbon::parse($timestamp, $fromTz);

            return $dt->setTimezone($timezone)->format($format);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function parseKey(string $key): array
    {
        $keyParts = explode('.', $key);
        $group = array_shift($keyParts);
        $setting = $keyParts[0] ?? null;
        $subKey = implode('.', $keyParts);

        return [$group, $setting, $subKey];
    }

    protected static function fetchSetting(string $group, string $setting): array
    {
        $tableName = config('settings.table_name', 'settings');

        /** @var \stdClass|null $item */
        $item = DB::table($tableName)
            ->where('group', $group)
            ->where('key', $setting)
            ->first();

        if ($item === null || ! property_exists($item, 'value')) {
            return [];
        }

        return [
            $setting => json_decode($item->value, true),
        ];
    }

    protected static function storeSetting(string $group, string $setting, mixed $value): void
    {
        $tableName = config('settings.table_name', 'settings');

        try {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Unable to serialize value to JSON: ' . $e->getMessage(), 0, $e);
        }

        DB::table($tableName)->upsert(
            [
                [
                    'group' => $group,
                    'key' => $setting,
                    'value' => $encoded,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['group', 'key'], // unique by
            ['settings', 'updated_at'] // columns to update on duplicate
        );
    }

    protected static function getCacheKey(string $group, string $setting): string
    {
        $prefix = config('settings.cache.prefix', 'settings');

        return "{$prefix}.{$group}.{$setting}";
    }
}