<?php

declare(strict_types=1);

namespace Nhwin\Settings\Support;

use DateTimeImmutable;

final readonly class SettingsAuditEntry
{
    public function __construct(
        public int|string|null $actorId,
        public ?string $actorType,
        public string $scope,
        public string $group,
        public ?string $key,
        public string $action,
        public mixed $oldValue,
        public mixed $newValue,
        public DateTimeImmutable $createdAt,
    ) {}
}
