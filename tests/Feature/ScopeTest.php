<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nhwin\Settings\Facades\Setting;

it('isolates settings and cache entries by scope', function (): void {
    Setting::set('general.site_name', 'Global');
    Setting::forScope('tenant:15')->set('general.site_name', 'Tenant 15');
    Setting::forScope('tenant:16')->set('general.site_name', 'Tenant 16');

    expect(Setting::get('general.site_name'))->toBe('Global')
        ->and(Setting::forScope('tenant:15')->get('general.site_name'))->toBe('Tenant 15')
        ->and(Setting::forScope('tenant:16')->get('general.site_name'))->toBe('Tenant 16')
        ->and(Cache::has('settings:global:general'))->toBeTrue()
        ->and(Cache::has('settings:tenant:15:general'))->toBeTrue()
        ->and(DB::table('settings')->where('group', 'general')->count())->toBe(3);
});

it('keeps the singleton manager scope unchanged', function (): void {
    $tenant = settings()->forScope('tenant:15');
    $tenant->set('general.name', 'Tenant');

    expect(settings('general.name', 'Global default'))->toBe('Global default')
        ->and($tenant->get('general.name'))->toBe('Tenant');
});
