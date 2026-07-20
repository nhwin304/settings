<?php

declare(strict_types=1);

namespace Nhwin\Settings\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;

final class SettingsCache
{
    public function __construct(private Repository $cache) {}

    /**
     * @param  Closure(): array<string, mixed>  $loader
     * @return array<string, mixed>
     */
    public function remember(string $scope, string $group, Closure $loader): array
    {
        $key = $this->key($scope, $group);
        $ttl = config('settings.cache.ttl');

        if ($ttl === null || (int) $ttl === 0) {
            /** @var array<string, mixed> */
            return $this->cache->rememberForever($key, $loader);
        }

        /** @var array<string, mixed> */
        return $this->cache->remember($key, (int) $ttl * 60, $loader);
    }

    public function forget(string $scope, string $group): void
    {
        $this->cache->forget($this->key($scope, $group));
    }

    public function key(string $scope, string $group): string
    {
        $prefix = (string) config('settings.cache.prefix', 'settings');

        return "{$prefix}:{$scope}:{$group}";
    }
}
