<?php

declare(strict_types=1);

use Nhwin\Settings\Contracts\SettingsManagerContract;
use Nhwin\Settings\Facades\Setting as SettingFacade;

if (! function_exists('settings')) {

    /**
     * Retrieve a configuration value from the database.
     *
     * @param  string  $key  Dot-notation key (vd: "general.brand_name").
     * @param  mixed  $default  Giá trị mặc định nếu không tìm thấy.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app(SettingsManagerContract::class);
        }

        return SettingFacade::get($key, $default);
    }
}
