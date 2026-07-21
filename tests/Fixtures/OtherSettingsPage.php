<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

use Filament\Notifications\Notification;
use Nhwin\Settings\Abstracts\AbstractPageSettings;

final class OtherSettingsPage extends AbstractPageSettings
{
    protected string $view = 'settings::filament.pages.settings-hub';

    protected function settingName(): string
    {
        return 'other';
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }
}
