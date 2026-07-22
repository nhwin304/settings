<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Nhwin\Settings\Events\SettingsGroupUpdated;
use Nhwin\Settings\Events\SettingUpdated;
use Nhwin\Settings\Exceptions\InvalidSettingType;
use Nhwin\Settings\Facades\Setting;
use Nhwin\Settings\Tests\Fixtures\CastingSettingsDefinition;
use Nhwin\Settings\Tests\Fixtures\FakeSettingsForm;
use Nhwin\Settings\Tests\Fixtures\GeneralSettingsDefinition;
use Nhwin\Settings\Tests\Fixtures\TestSettingsPage;

beforeEach(function (): void {
    config()->set('settings.definitions', [GeneralSettingsDefinition::class]);
});

it('applies optional defaults casts and definition encryption', function (): void {
    expect(Setting::getGroup('general'))->toBe([
        'site_name' => '',
        'maintenance' => false,
    ]);

    Setting::setMany('general', [
        'site_name' => '304',
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

it('preserves ciphertext when unrelated fields are saved or a secret is omitted', function (): void {
    Setting::setMany('general', [
        'site_name' => 'Before',
        'api' => ['token' => 'keep-secret', 'endpoint' => 'old'],
    ]);
    $before = DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value');

    Setting::setMany('general', ['site_name' => 'After']);
    $afterUnrelated = DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value');

    Setting::setMany('general', ['api' => ['endpoint' => 'new']]);
    $afterSibling = DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value');

    expect($afterUnrelated)->toBe($before)
        ->and($afterSibling)->not->toBe($before)
        ->and(Setting::get('general.api.token'))->toBe('keep-secret')
        ->and(Setting::get('general.api.endpoint'))->toBe('new');
});

it('preserves ciphertext for blank and unchanged secret input without events', function (): void {
    Event::fake([SettingUpdated::class, SettingsGroupUpdated::class]);
    Setting::setMany('general', ['api' => ['token' => 'keep-secret']]);
    $before = DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value');

    Setting::setMany('general', ['api' => ['token' => '']]);
    Setting::setMany('general', ['api' => ['token' => 'keep-secret']]);

    expect(DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value'))
        ->toBe($before)
        ->and(Setting::get('general.api.token'))->toBe('keep-secret');
    Event::assertDispatchedTimes(SettingUpdated::class, 1);
    Event::assertDispatchedTimes(SettingsGroupUpdated::class, 1);
});

it('replaces a changed secret once and redacts its events', function (): void {
    Event::fake([SettingUpdated::class, SettingsGroupUpdated::class]);
    Setting::setMany('general', ['api' => ['token' => 'old-secret']]);
    $before = DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value');

    Setting::setMany('general', ['api' => ['token' => 'new-secret']]);
    $after = DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value');

    expect($after)->not->toBe($before)
        ->and($after)->not->toContain('new-secret')
        ->and(Setting::get('general.api.token'))->toBe('new-secret');
    Event::assertDispatched(SettingUpdated::class, fn (SettingUpdated $event): bool => $event->oldValue === ['token' => '[encrypted]']
        && $event->newValue === ['token' => '[encrypted]']
    );
    Event::assertDispatchedTimes(SettingUpdated::class, 2);
});

it('clears an encrypted value only through the explicit clear action', function (): void {
    Setting::setMany('general', ['api' => ['token' => 'clear-me', 'endpoint' => 'keep']]);

    Setting::clearEncrypted('general.api.token');

    expect(Setting::get('general.api.token'))->toBeNull()
        ->and(Setting::get('general.api.endpoint'))->toBe('keep')
        ->and(DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value'))
        ->not->toContain('clear-me', '__nhwin_encrypted');
});

it('does not fill a settings form with decrypted secrets', function (): void {
    Setting::setMany('general', [
        'site_name' => 'Site',
        'api' => ['token' => 'form-secret'],
    ]);
    $page = new TestSettingsPage;
    $page->testForm = new FakeSettingsForm;

    $page->mount();

    expect($page->testForm->state)
        ->toMatchArray(['site_name' => 'Site', 'api' => ['token' => '']])
        ->not->toContain('form-secret');
});

it('removes plaintext secrets from public and form state after save', function (): void {
    $page = new TestSettingsPage;
    $page->testForm = new FakeSettingsForm;
    $page->mount();
    $page->testForm->state = [
        'site_name' => 'Site',
        'api' => ['token' => 'browser-secret'],
    ];

    $page->save();

    $stored = DB::table('settings')->where('group', 'general')->where('key', 'api')->value('value');

    expect($stored)->not->toContain('browser-secret')
        ->and(Setting::get('general.api.token'))->toBe('browser-secret')
        ->and($page->data)->toMatchArray(['api' => ['token' => '']])
        ->and($page->data)->not->toContain('browser-secret')
        ->and($page->testForm->state)->toMatchArray(['api' => ['token' => '']])
        ->and($page->testForm->state)->not->toContain('browser-secret');
});

it('casts supported boolean representations deterministically', function (mixed $stored, bool $expected): void {
    Setting::set('general.maintenance', $stored);

    expect(Setting::get('general.maintenance'))->toBe($expected);
})->with([
    [true, true],
    [false, false],
    [1, true],
    [0, false],
    ['1', true],
    ['0', false],
    ['true', true],
    ['false', false],
    ['yes', true],
    ['no', false],
    ['on', true],
    ['off', false],
]);

it('rejects ambiguous boolean casts without including the stored value', function (): void {
    Setting::set('general.maintenance', 'sensitive-random-value');

    try {
        Setting::get('general.maintenance');
    } catch (Throwable $exception) {
        expect($exception)
            ->toBeInstanceOf(InvalidSettingType::class)
            ->and($exception->getMessage())->not->toContain('sensitive-random-value');

        return;
    }

    $this->fail('An invalid boolean cast did not throw an exception.');
});

it('normalizes every supported definition cast without permissive coercion', function (): void {
    config()->set('settings.definitions', [CastingSettingsDefinition::class]);
    Setting::setMany('casting', [
        'string_value' => '304',
        'integer_value' => 304,
        'float_value' => 304,
        'boolean_value' => 'yes',
        'array_value' => ['valid'],
    ]);

    expect(Setting::getGroup('casting'))->toMatchArray([
        'string_value' => '304',
        'integer_value' => 304,
        'float_value' => 304.0,
        'boolean_value' => true,
        'array_value' => ['valid'],
    ]);
});

it('rejects invalid definition cast input without exposing its value', function (
    string $path,
    mixed $value,
): void {
    config()->set('settings.definitions', [CastingSettingsDefinition::class]);
    Setting::set("casting.{$path}", $value);

    try {
        Setting::get("casting.{$path}");
    } catch (Throwable $exception) {
        expect($exception)
            ->toBeInstanceOf(InvalidSettingType::class)
            ->and($exception->getMessage())->not->toContain('sensitive-invalid');

        return;
    }

    $this->fail("Invalid definition cast [{$path}] did not throw an exception.");
})->with([
    'string' => ['string_value', 304],
    'integer string' => ['integer_value', '304'],
    'integer text' => ['integer_value', 'sensitive-invalid'],
    'float string' => ['float_value', '304.5'],
    'boolean' => ['boolean_value', 'sensitive-invalid'],
    'array' => ['array_value', 'sensitive-invalid'],
]);
