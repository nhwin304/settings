<?php

use Nhwin\Settings\Support\SettingKey;

it('parses root and nested setting keys', function (string $key, string $group, string $root, ?string $nested): void {
    $parsed = SettingKey::parse($key);

    expect($parsed->group)->toBe($group)
        ->and($parsed->root)->toBe($root)
        ->and($parsed->nestedPath)->toBe($nested);
})->with([
    ['general.site_name', 'general', 'site_name', null],
    ['general.social.facebook', 'general', 'social', 'facebook'],
    ['general.social.links.facebook', 'general', 'social', 'links.facebook'],
]);

it('rejects malformed setting keys', function (string $key): void {
    SettingKey::parse($key);
})->with(['general', '.name', 'general.', 'general..name'])->throws(InvalidArgumentException::class);
