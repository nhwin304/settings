<?php

declare(strict_types=1);

namespace Nhwin\Settings\Contracts;

use Illuminate\Support\Collection;

interface SettingsManagerContract
{
    public function forScope(string $scope): static;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function setEncrypted(string $key, mixed $value): void;

    /** @param array<string, mixed> $values */
    public function setMany(string $group, array $values): void;

    /** @return array<string, mixed> */
    public function getGroup(string $group): array;

    public function getGroupLastUpdatedAt(
        string $group,
        string $format = 'H:i:s d/m/Y',
        ?string $timezone = null,
    ): ?string;

    public function string(string $key, ?string $default = null): string;

    public function integer(string $key, ?int $default = null): int;

    public function float(string $key, ?float $default = null): float;

    public function boolean(string $key, ?bool $default = null): bool;

    /**
     * @param  array<mixed>|null  $default
     * @return array<mixed>
     */
    public function array(string $key, ?array $default = null): array;

    /**
     * @param  array<mixed>|null  $default
     * @return Collection<array-key, mixed>
     */
    public function collection(string $key, ?array $default = null): Collection;
}
