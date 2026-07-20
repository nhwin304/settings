<?php

use Illuminate\Support\Facades\DB;
use Nhwin\Settings\Facades\Setting;
use Nhwin\Settings\Tests\Fixtures\GeneralSettingsDefinition;

beforeEach(function (): void {
    config()->set('settings.definitions', [GeneralSettingsDefinition::class]);
});

it('applies optional defaults casts and definition encryption', function (): void {
    expect(Setting::getGroup('general'))->toBe([
        'site_name' => '',
        'maintenance' => false,
    ]);

    Setting::setMany('general', [
        'site_name' => 304,
        'maintenance' => 1,
        'api' => ['token' => 'definition-secret'],
    ]);

    expect(Setting::get('general.site_name'))->toBe('304')
        ->and(Setting::get('general.maintenance'))->toBeTrue()
        ->and(Setting::get('general.api.token'))->toBe('definition-secret')
        ->and(DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value'))
        ->not->toContain('definition-secret');
});

it('applies definition encryption to a direct nested set', function (): void {
    Setting::set('general.api.token', 'direct-secret');

    expect(Setting::get('general.api.token'))->toBe('direct-secret')
        ->and(DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value'))
        ->not->toContain('direct-secret');
});
