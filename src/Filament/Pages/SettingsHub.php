<?php

declare(strict_types=1);

namespace Nhwin\Settings\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Nhwin\Settings\Filament\Plugins\SettingsPlugin;
use UnitEnum;

class SettingsHub extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected string $view = 'settings::filament.pages.settings-hub';

    public static function canAccess(): bool
    {
        return SettingsPlugin::get()->isAccessible();
    }

    /** @return list<\Nhwin\Settings\Filament\SettingsPageDefinition> */
    public function getSettingsPages(): array
    {
        return SettingsPlugin::get()->registry()->accessible();
    }
}
