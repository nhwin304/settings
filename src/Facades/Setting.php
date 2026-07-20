<?php

namespace Nhwin\Settings\Facades;

use Illuminate\Support\Facades\Facade;
use Nhwin\Settings\Contracts\SettingsManagerContract;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static SettingsManagerContract forScope(string $scope)
 * @method static void set(string $key, mixed $value)
 * @method static void setEncrypted(string $key, mixed $value)
 * @method static void setMany(string $group, array<string, mixed> $values)
 * @method static array<string, mixed> getGroup(string $group)
 * @method static string|null getGroupLastUpdatedAt(string $group, string $format = 'H:i:s d/m/Y', ?string $timezone = null)
 * @method static string string(string $key, ?string $default = null)
 * @method static int integer(string $key, ?int $default = null)
 * @method static float float(string $key, ?float $default = null)
 * @method static bool boolean(string $key, ?bool $default = null)
 * @method static array<mixed> array(string $key, array<mixed>|null $default = null)
 * @method static \Illuminate\Support\Collection<array-key, mixed> collection(string $key, array<mixed>|null $default = null)
 *
 * @mixin SettingsManagerContract
 *
 * @see SettingsManagerContract
 */
class Setting extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManagerContract::class;
    }
}
