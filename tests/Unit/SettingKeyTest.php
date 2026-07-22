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
})->with([
    'missing root' => 'general',
    'empty group' => '.name',
    'empty root' => 'general.',
    'empty nested segment' => 'general..name',
    'control character' => "general.site\nname",
    'long group' => str_repeat('g', 256).'.name',
    'long root' => 'general.'.str_repeat('k', 256),
    'long nested segment' => 'general.root.'.str_repeat('n', 256),
    'long full path' => 'general.root.'.implode('.', array_fill(0, 5, str_repeat('n', 210))),
])->throws(InvalidArgumentException::class);
