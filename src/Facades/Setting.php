<?php

namespace Nhwin304\Settings\Facades;

use Illuminate\Support\Facades\Facade;
use Nhwin304\Supports\Setting as SettingService;

/**
 * 
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static array|null getGroup(string $group)
 * @method static string|null getGroupLastUpdatedAt(string $group, string $format = 'H:i:s d/m/Y', ?string $timezone = null)
 *
 * @mixin \Nhwin304\Supports\Setting
 * @see \Nhwin304\Supports\Setting
 */

class Setting extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SettingService::class;
    }
}