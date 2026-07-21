<?php

declare(strict_types=1);

namespace Nhwin\Settings\Events;

final readonly class SettingsGroupDeleted
{
    /**
     * @param  list<string>  $deletedKeys
     * @param  array<string, mixed>  $oldValues
     */
    public function __construct(
        public string $scope,
        public string $group,
        public array $deletedKeys,
        public array $oldValues,
    ) {}
}
