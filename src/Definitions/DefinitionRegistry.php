<?php

declare(strict_types=1);

namespace Nhwin\Settings\Definitions;

final class DefinitionRegistry
{
    /** @var array<string, SettingsDefinition> */
    private array $definitions = [];

    /** @param iterable<SettingsDefinition> $definitions */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition::group()] = $definition;
        }
    }

    public function get(string $group): ?SettingsDefinition
    {
        return $this->definitions[$group] ?? null;
    }
}
