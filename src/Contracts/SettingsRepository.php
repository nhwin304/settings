<?php

declare(strict_types=1);

namespace Nhwin\Settings\Contracts;

use Carbon\CarbonInterface;

interface SettingsRepository
{
    /** @return array<string, mixed> */
    public function getGroup(string $scope, string $group): array;

    /** @param array<string, mixed> $values */
    public function setMany(string $scope, string $group, array $values): void;

    public function forget(string $scope, string $group, string $key): void;

    public function forgetGroup(string $scope, string $group): void;

    public function lastUpdatedAt(string $scope, string $group): ?CarbonInterface;
}
