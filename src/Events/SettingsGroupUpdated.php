<?php

declare(strict_types=1);

namespace Nhwin\Settings\Events;

final readonly class SettingsGroupUpdated
{
    /**
     * @param  list<string>  $changedKeys
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function __construct(
        public string $scope,
        public string $group,
        public array $changedKeys,
        public array $oldValues,
        public array $newValues,
    ) {}
}
