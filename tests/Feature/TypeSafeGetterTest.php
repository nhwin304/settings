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

it('returns integer and float JSON numbers as floats', function (): void {
    Setting::setMany('types', ['integer_fee' => 5, 'float_fee' => 5.5]);

    expect(Setting::float('types.integer_fee'))->toBe(5.0)
        ->and(Setting::float('types.float_fee'))->toBe(5.5);
});

it('uses a float default when the setting is absent', function (): void {
    expect(Setting::float('types.missing_fee', 2.5))->toBe(2.5);
});

it('rejects numeric strings in the float getter', function (): void {
    Setting::set('types.fee', '5.5');

    Setting::float('types.fee');
})->throws(InvalidSettingType::class, "Setting 'types.fee' must be float; string was stored.");
