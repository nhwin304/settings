<?php

declare(strict_types=1);

namespace Nhwin\Settings\Definitions;

abstract class SettingsDefinition
{
    abstract public static function group(): string;

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [];
    }

    /** @return array<string, 'string'|'integer'|'float'|'boolean'|'array'> */
    public function casts(): array
    {
        return [];
    }

    /** @return list<string> */
    public function encrypted(): array
    {
        return [];
    }
}
