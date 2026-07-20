<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Nhwin\Settings\Events\SettingsGroupUpdated;
use Nhwin\Settings\Events\SettingUpdated;
use Nhwin\Settings\Facades\Setting;

it('stores explicit encrypted values as ciphertext and returns plaintext', function (): void {
    Setting::setEncrypted('mail.smtp.password', 'super-secret');

    $payload = DB::table('settings')->where('group', 'mail')->where('key', 'smtp')->value('value');

    expect($payload)->not->toContain('super-secret')
        ->and(Setting::get('mail.smtp.password'))->toBe('super-secret');
});

it('preserves encrypted siblings during unrelated nested updates', function (): void {
    Setting::setEncrypted('mail.smtp.password', 'super-secret');
    Setting::set('mail.smtp.host', 'smtp.example.test');

    expect(Setting::get('mail.smtp.password'))->toBe('super-secret')
        ->and(Setting::get('mail.smtp.host'))->toBe('smtp.example.test')
        ->and(DB::table('settings')->where('group', 'mail')->value('value'))
        ->not->toContain('super-secret');
});

it('fails predictably for a corrupted encrypted payload', function (): void {
    Setting::setEncrypted('mail.password', 'super-secret');
    DB::table('settings')->where('group', 'mail')->where('key', 'password')->update([
        'value' => json_encode(['__nhwin_encrypted' => 'corrupted']),
    ]);
    Cache::flush();

    Setting::get('mail.password');
})->throws(DecryptException::class);

it('redacts encrypted values from committed domain events', function (): void {
    Event::fake([SettingUpdated::class, SettingsGroupUpdated::class]);

    Setting::setEncrypted('mail.password', 'super-secret');

    Event::assertDispatched(SettingUpdated::class, fn (SettingUpdated $event): bool => $event->newValue === '[encrypted]'
        && $event->scope === 'global'
        && $event->group === 'mail'
    );
    Event::assertDispatched(SettingsGroupUpdated::class);
});
