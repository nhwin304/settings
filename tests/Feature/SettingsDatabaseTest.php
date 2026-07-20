<?php

use Illuminate\Support\Facades\DB;
use Nhwin\Settings\Facades\Setting;

it('uses one row per root key for a bulk group write', function (): void {
    Setting::setMany('database', ['first' => 1, 'second' => 2]);

    expect(DB::table('settings')->where('scope', 'global')->where('group', 'database')->count())->toBe(2);
});
