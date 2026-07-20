<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

use Nhwin\Settings\Definitions\SettingsDefinition;

final class GeneralSettingsDefinition extends SettingsDefinition
{
    public static function group(): string
    {
        return 'general';
    }

    public function defaults(): array
    {
        return ['site_name' => '', 'maintenance' => false];
    }

    public function casts(): array
    {
        return ['site_name' => 'string', 'maintenance' => 'boolean'];
    }

    public function encrypted(): array
    {
        return ['api.token'];
    }
}
