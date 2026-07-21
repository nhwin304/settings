<?php

declare(strict_types=1);

namespace Nhwin\Settings\Events;

final readonly class SettingDeleted
{
    public function __construct(
        public string $scope,
        public string $group,
        public string $key,
        public mixed $oldValue,
    ) {}
}
