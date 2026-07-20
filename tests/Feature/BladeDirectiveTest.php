<?php

use Illuminate\Support\Facades\Blade;
use Nhwin\Settings\Facades\Setting;

it('keeps the escaped Blade directive API', function (): void {
    Setting::set('blade.title', '<strong>Nhwin</strong>');

    expect(Blade::render("@settings('blade.title')"))->toBe('&lt;strong&gt;Nhwin&lt;/strong&gt;');
});
