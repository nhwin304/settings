<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

use Nhwin\Settings\Definitions\SettingsDefinition;

final class CastingSettingsDefinition extends SettingsDefinition
{
    public static function group(): string
    {
        return 'casting';
    }

    public function casts(): array
    {
        return [
            'string_value' => 'string',
            'integer_value' => 'integer',
            'float_value' => 'float',
            'boolean_value' => 'boolean',
            'array_value' => 'array',
        ];
    }
}
