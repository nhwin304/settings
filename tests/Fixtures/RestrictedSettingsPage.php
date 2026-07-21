<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

use Nhwin\Settings\Abstracts\AbstractPageSettings;

final class RestrictedSettingsPage extends AbstractPageSettings
{
    protected string $view = 'settings::filament.pages.settings-hub';

    protected static function canAccessSettingsPage(): bool
    {
        return false;
    }

    protected function settingName(): string
    {
        return 'restricted';
    }
}
