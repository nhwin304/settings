<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Nhwin\Settings\Events\SettingDeleted;
use Nhwin\Settings\Events\SettingsGroupDeleted;
use Nhwin\Settings\Facades\Setting;

it('forgets a root setting while preserving its group siblings', function (): void {
    Setting::setMany('general', ['legacy' => 'remove', 'site_name' => 'keep']);

    Setting::forget('general.legacy');

    expect(Setting::get('general.legacy', 'missing'))->toBe('missing')
        ->and(Setting::get('general.site_name'))->toBe('keep')
        ->and(DB::table('settings')->where('group', 'general')->where('key', 'legacy')->exists())->toBeFalse();
});

it('forgets a nested setting while preserving siblings', function (): void {
    Setting::set('general.social', [
        'facebook' => 'remove',
        'github' => 'keep',
    ]);

    Setting::forget('general.social.facebook');

    expect(Setting::get('general.social'))->toBe(['github' => 'keep'])
        ->and(DB::table('settings')->where('group', 'general')->where('key', 'social')->exists())->toBeTrue();
});

it('deletes an empty root row after its last nested setting is forgotten', function (): void {
    Setting::set('general.social.facebook', 'remove');

    Setting::forget('general.social.facebook');

    expect(Setting::get('general.social', 'missing'))->toBe('missing')
        ->and(DB::table('settings')->where('group', 'general')->where('key', 'social')->exists())->toBeFalse();
});

it('forgets a complete group only in the selected scope', function (): void {
    Setting::set('general.site_name', 'Global');
    Setting::forScope('tenant:1')->set('general.site_name', 'Tenant');

    Setting::forScope('tenant:1')->forgetGroup('general');

    expect(Setting::get('general.site_name'))->toBe('Global')
        ->and(Setting::forScope('tenant:1')->get('general.site_name', 'missing'))->toBe('missing')
        ->and(DB::table('settings')->where('scope', 'tenant:1')->where('group', 'general')->exists())->toBeFalse();
});

it('invalidates group cache and dispatches deletion events after commit', function (): void {
    Event::fake([SettingDeleted::class, SettingsGroupDeleted::class]);
    Setting::setMany('general', ['legacy' => 'remove', 'site_name' => 'keep']);
    Setting::getGroup('general');
    expect(Cache::has('settings:global:general'))->toBeTrue();

    Setting::forget('general.legacy');

    expect(Cache::has('settings:global:general'))->toBeFalse();
    Event::assertDispatched(SettingDeleted::class, fn (SettingDeleted $event): bool => $event->scope === 'global'
        && $event->group === 'general'
        && $event->key === 'legacy'
        && $event->oldValue === 'remove'
    );

    Setting::get('general.site_name');
    Setting::forgetGroup('general');

    expect(Cache::has('settings:global:general'))->toBeFalse();
    Event::assertDispatched(SettingsGroupDeleted::class, fn (SettingsGroupDeleted $event): bool => $event->deletedKeys === ['site_name']
        && $event->oldValues === ['site_name' => 'keep']
    );
});

it('redacts encrypted values from deletion events', function (): void {
    Event::fake([SettingDeleted::class, SettingsGroupDeleted::class]);
    Setting::setEncrypted('mail.smtp.password', 'delete-secret');

    Setting::forget('mail.smtp.password');

    Event::assertDispatched(SettingDeleted::class, fn (SettingDeleted $event): bool => $event->oldValue === '[encrypted]'
        && ! str_contains(serialize($event), 'delete-secret')
    );
});

it('keeps cache intact when a delete transaction fails', function (): void {
    Setting::set('general.legacy', 'keep');
    expect(Setting::get('general.legacy'))->toBe('keep')
        ->and(Cache::has('settings:global:general'))->toBeTrue();
    DB::unprepared("CREATE TRIGGER prevent_settings_delete BEFORE DELETE ON settings BEGIN SELECT RAISE(FAIL, 'blocked'); END");

    expect(fn () => Setting::forget('general.legacy'))->toThrow(QueryException::class)
        ->and(Cache::has('settings:global:general'))->toBeTrue()
        ->and(Setting::get('general.legacy'))->toBe('keep');
});
