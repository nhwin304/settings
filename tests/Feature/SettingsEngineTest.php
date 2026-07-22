<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nhwin\Settings\Facades\Setting;

beforeEach(function (): void {
    Cache::flush();
});

it('supports scalar, array, nested, default and null reads', function (): void {
    Setting::setMany('general', [
        'site_name' => 'Nhwin',
        'social' => [
            'facebook' => 'old',
            'links' => ['github' => 'nhwin304'],
        ],
        'nullable' => null,
    ]);

    expect(Setting::get('general.site_name'))->toBe('Nhwin')
        ->and(Setting::get('general.social'))->toBeArray()
        ->and(Setting::get('general.social.facebook'))->toBe('old')
        ->and(Setting::get('general.social.links.github'))->toBe('nhwin304')
        ->and(Setting::get('general.missing', 'fallback'))->toBe('fallback')
        ->and(Setting::get('general.nullable', 'fallback'))->toBeNull();
});

it('updates a nested key without replacing its siblings', function (): void {
    Setting::set('general.social', [
        'facebook' => 'old',
        'github' => 'nhwin304',
        'links' => ['website' => 'https://example.test'],
    ]);

    Setting::set('general.social.facebook', 'new');
    Setting::set('general.social.links.docs', 'https://docs.example.test');

    expect(Setting::get('general.social'))->toBe([
        'facebook' => 'new',
        'github' => 'nhwin304',
        'links' => [
            'website' => 'https://example.test',
            'docs' => 'https://docs.example.test',
        ],
    ]);
});

it('bases nested mutations on the latest committed database root', function (): void {
    Setting::set('general.social', [
        'facebook' => 'old',
        'github' => 'old',
    ]);
    Setting::get('general.social');

    DB::table('settings')
        ->where('scope', 'global')
        ->where('group', 'general')
        ->where('key', 'social')
        ->update(['value' => json_encode([
            'facebook' => 'old',
            'github' => 'latest-committed',
        ], JSON_THROW_ON_ERROR)]);

    Setting::set('general.social.facebook', 'new');

    expect(Setting::get('general.social'))->toBe([
        'facebook' => 'new',
        'github' => 'latest-committed',
    ]);
});

it('reads a group from the database only once while cached', function (): void {
    Setting::setMany('general', ['site_name' => 'Nhwin', 'maintenance' => false]);
    Cache::flush();

    $selects = 0;
    DB::listen(function ($query) use (&$selects): void {
        if (str_starts_with(strtolower($query->sql), 'select')) {
            $selects++;
        }
    });

    Setting::get('general.site_name');
    Setting::get('general.maintenance');

    expect($selects)->toBe(1)
        ->and(Cache::has('settings:global:general'))->toBeTrue();
});

it('invalidates group cache after a successful bulk write', function (): void {
    Setting::set('general.site_name', 'Before');
    expect(Setting::get('general.site_name'))->toBe('Before');

    settings()->setMany('general', ['site_name' => 'After', 'maintenance' => true]);

    expect(Setting::get('general.site_name'))->toBe('After')
        ->and(Setting::get('general.maintenance'))->toBeTrue();
});

it('keeps cached data consistent when a write transaction fails', function (): void {
    Setting::set('general.fee', 10);
    expect(Setting::get('general.fee'))->toBe(10);

    expect(fn () => Setting::set('general.fee', NAN))->toThrow(RuntimeException::class)
        ->and(Setting::get('general.fee'))->toBe(10)
        ->and(DB::table('settings')->where('group', 'general')->where('key', 'fee')->value('value'))
        ->toBe('10');
});
