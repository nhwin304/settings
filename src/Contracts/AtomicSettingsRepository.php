<?php

declare(strict_types=1);

namespace Nhwin\Settings\Contracts;

use Closure;

interface AtomicSettingsRepository
{
    /** @param Closure(mixed): mixed $callback */
    public function mutate(
        string $scope,
        string $group,
        string $key,
        Closure $callback,
    ): mixed;
}
