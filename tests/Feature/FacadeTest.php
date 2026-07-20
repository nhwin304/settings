<?php

use Nhwin\Settings\Contracts\SettingsManagerContract;
use Nhwin\Settings\Facades\Setting;

it('binds the facade to the settings manager contract', function (): void {
    expect(Setting::getFacadeRoot())->toBe(app(SettingsManagerContract::class));
});
