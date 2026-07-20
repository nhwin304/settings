<?php

use Nhwin\Settings\Exceptions\InvalidSettingType;
use Nhwin\Settings\Facades\Setting;

it('returns values through strict type-safe getters', function (): void {
    Setting::setMany('types', [
        'name' => 'Nhwin',
        'count' => 12,
        'ratio' => 1.5,
        'enabled' => true,
        'links' => ['github' => 'nhwin304'],
    ]);

    expect(Setting::string('types.name'))->toBe('Nhwin')
        ->and(Setting::integer('types.count'))->toBe(12)
        ->and(Setting::float('types.ratio'))->toBe(1.5)
        ->and(Setting::boolean('types.enabled'))->toBeTrue()
        ->and(Setting::array('types.links'))->toBe(['github' => 'nhwin304'])
        ->and(Setting::collection('types.links')->get('github'))->toBe('nhwin304');
});

it('throws a clear exception for a mismatched stored type', function (): void {
    Setting::set('types.count', '12');

    Setting::integer('types.count');
})->throws(InvalidSettingType::class, "Setting 'types.count' must be integer; string was stored.");
