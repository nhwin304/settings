<?php

declare(strict_types=1);

namespace Nhwin\Settings\Events;

final readonly class SettingUpdated
{
    public function __construct(
        public string $scope,
        public string $group,
        public string $key,
        public mixed $oldValue,
        public mixed $newValue,
    ) {}
}
