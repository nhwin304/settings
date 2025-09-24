<?php

declare(strict_types=1);

use Nhwin304\Settings\Facades\Setting as SettingFacade;

if (! function_exists('settings')) {
    
    /**
     * Retrieve a configuration value from the database.
     *
     * @param  string $key      Dot-notation key (vd: "general.brand_name").
     * @param  mixed  $default  Giá trị mặc định nếu không tìm thấy.
     * @return mixed
     */
    function settings(string $key, mixed $default = null): mixed
    {
        return SettingFacade::get($key, $default);
    }
}
