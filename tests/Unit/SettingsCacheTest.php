<?php

use Illuminate\Contracts\Cache\Repository;
use Nhwin\Settings\Support\SettingsCache;

it('uses forever caching when ttl is null or zero', function (mixed $ttl): void {
    config()->set('settings.cache.ttl', $ttl);
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('rememberForever')
        ->once()
        ->with('settings:global:general', Mockery::type(Closure::class))
        ->andReturn(['site_name' => 'Nhwin']);

    $cache = new SettingsCache($repository);

    expect($cache->remember('global', 'general', fn (): array => []))
        ->toBe(['site_name' => 'Nhwin']);
})->with([null, 0]);

it('converts configured cache minutes to seconds', function (): void {
    config()->set('settings.cache.ttl', 5);
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('remember')
        ->once()
        ->with('settings:global:general', 300, Mockery::type(Closure::class))
        ->andReturn(['site_name' => 'Nhwin']);

    expect((new SettingsCache($repository))->remember('global', 'general', fn (): array => []))
        ->toBe(['site_name' => 'Nhwin']);
});
