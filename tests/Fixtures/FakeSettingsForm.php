<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

final class FakeSettingsForm
{
    public int $relationshipSaves = 0;

    /** @param array<string, mixed> $state */
    public function __construct(public array $state = []) {}

    /** @param array<string, mixed> $data */
    public function fill(array $data): void
    {
        $this->state = $data;
    }

    /** @return array<string, mixed> */
    public function getState(): array
    {
        return $this->state;
    }

    public function saveRelationships(): void
    {
        $this->relationshipSaves++;
    }
}
