<?php

use Nhwin\Settings\Contracts\ScopeResolver;
use Nhwin\Settings\Contracts\SettingsManagerContract;
use Nhwin\Settings\Facades\Setting;
use Nhwin\Settings\Tests\Fixtures\MutableScopeResolver;

it('rejects invalid explicit scopes before cloning the manager', function (string $scope): void {
    Setting::forScope($scope);
})->with([
    'empty' => '',
    'control character' => "tenant\n1",
    'too long' => str_repeat('s', 256),
])->throws(InvalidArgumentException::class);

it('rejects an invalid scope returned by the resolver', function (): void {
    app()->instance(ScopeResolver::class, new MutableScopeResolver(''));
    app()->forgetInstance(SettingsManagerContract::class);
    Setting::clearResolvedInstance(SettingsManagerContract::class);

    Setting::get('general.site_name');
})->throws(InvalidArgumentException::class);

it('rejects invalid direct group names', function (string $group): void {
    Setting::getGroup($group);
})->with([
    'empty' => '',
    'dot separator' => 'invalid.group',
    'control character' => "general\r",
    'too long' => str_repeat('g', 256),
])->throws(InvalidArgumentException::class);

it('rejects invalid bulk root keys', function (array $values): void {
    Setting::setMany('general', $values);
})->with([
    'numeric root' => [[0 => 'value']],
    'dot separator' => [['invalid.root' => 'value']],
    'control character' => [["invalid\troot" => 'value']],
    'too long' => [[str_repeat('r', 256) => 'value']],
])->throws(InvalidArgumentException::class);
