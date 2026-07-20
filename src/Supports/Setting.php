<?php

declare(strict_types=1);

namespace Nhwin\Settings\Supports;

use Nhwin\Settings\Contracts\SettingsManagerContract;

/**
 * @deprecated Use SettingsManagerContract or the Setting facade. Kept for compatibility.
 */
class Setting
{
    public static function forScope(string $scope): SettingsManagerContract
    {
        return static::manager()->forScope($scope);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::manager()->get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::manager()->set($key, $value);
    }

    public static function setEncrypted(string $key, mixed $value): void
    {
        static::manager()->setEncrypted($key, $value);
    }

    /** @param array<string, mixed> $values */
    public static function setMany(string $group, array $values): void
    {
        static::manager()->setMany($group, $values);
    }

    /** @return array<string, mixed> */
    public static function getGroup(string $group): array
    {
        return static::manager()->getGroup($group);
    }

    public static function getGroupLastUpdatedAt(
        string $group,
        string $format = 'H:i:s d/m/Y',
        ?string $timezone = null,
    ): ?string {
        return static::manager()->getGroupLastUpdatedAt($group, $format, $timezone);
    }

    protected static function manager(): SettingsManagerContract
    {
        return app(SettingsManagerContract::class);
    }
}
