<?php

declare(strict_types=1);

namespace Nhwin\Settings\Presets;

final readonly class SettingsPreset
{
    /**
     * @param  list<array{type: 'TextInput'|'Textarea'|'Toggle', name: string, label: string}>  $fields
     * @param  array<string, mixed>  $defaults
     * @param  array<string, 'string'|'boolean'>  $casts
     * @param  list<string>  $encrypted
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $fields,
        public array $defaults,
        public array $casts,
        public array $encrypted = [],
    ) {}
}
