<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Nhwin\Settings\Contracts\ScopeResolver;
use Nhwin\Settings\Contracts\SettingsManagerContract;
use Nhwin\Settings\Facades\Setting;
use Nhwin\Settings\Tests\Fixtures\MutableScopeResolver;

it('isolates settings and cache entries by scope', function (): void {
    Setting::set('general.site_name', 'Global');
    Setting::forScope('tenant:15')->set('general.site_name', 'Tenant 15');
    Setting::forScope('tenant:16')->set('general.site_name', 'Tenant 16');

    expect(Setting::get('general.site_name'))->toBe('Global')
        ->and(Setting::forScope('tenant:15')->get('general.site_name'))->toBe('Tenant 15')
        ->and(Setting::forScope('tenant:16')->get('general.site_name'))->toBe('Tenant 16')
        ->and(Cache::has('settings:global:general'))->toBeTrue()
        ->and(Cache::has('settings:tenant:15:general'))->toBeTrue()
        ->and(DB::table('settings')->where('group', 'general')->count())->toBe(3);
});

it('keeps the singleton manager scope unchanged', function (): void {
    $tenant = settings()->forScope('tenant:15');
    $tenant->set('general.name', 'Tenant');

    expect(settings('general.name', 'Global default'))->toBe('Global default')
        ->and($tenant->get('general.name'))->toBe('Tenant');
});

it('resolves the default scope for every operation in a long running container', function (): void {
    $resolver = new MutableScopeResolver('tenant:1');
    app()->instance(ScopeResolver::class, $resolver);
    app()->forgetInstance(SettingsManagerContract::class);
    Setting::clearResolvedInstance(SettingsManagerContract::class);

    $manager = app(SettingsManagerContract::class);
    $manager->set('general.site_name', 'Tenant 1');

    $resolver->scope = 'tenant:2';
    $manager->set('general.site_name', 'Tenant 2');

    expect($manager->get('general.site_name'))->toBe('Tenant 2');

    $resolver->scope = 'tenant:1';
    expect($manager->get('general.site_name'))->toBe('Tenant 1')
        ->and(DB::table('settings')->where('scope', 'tenant:1')->value('value'))->toBe('"Tenant 1"')
        ->and(DB::table('settings')->where('scope', 'tenant:2')->value('value'))->toBe('"Tenant 2"');
});

it('keeps explicit scoped clones isolated while the resolver changes', function (): void {
    $resolver = new MutableScopeResolver('global:a');
    app()->instance(ScopeResolver::class, $resolver);
    app()->forgetInstance(SettingsManagerContract::class);
    Setting::clearResolvedInstance(SettingsManagerContract::class);

    $manager = app(SettingsManagerContract::class);
    $tenant1 = $manager->forScope('tenant:1');
    $tenant2 = $manager->forScope('tenant:2');

    $tenant1->set('general.site_name', 'Tenant 1');
    $tenant2->set('general.site_name', 'Tenant 2');
    $manager->set('general.site_name', 'Global A');

    $resolver->scope = 'global:b';
    $manager->set('general.site_name', 'Global B');

    expect($tenant1->get('general.site_name'))->toBe('Tenant 1')
        ->and($tenant2->get('general.site_name'))->toBe('Tenant 2')
        ->and($manager->get('general.site_name'))->toBe('Global B');

    $resolver->scope = 'global:a';
    expect($manager->get('general.site_name'))->toBe('Global A');
});
