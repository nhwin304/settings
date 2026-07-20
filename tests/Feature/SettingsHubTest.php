<?php

use Nhwin\Settings\Filament\Plugins\SettingsPlugin;
use Nhwin\Settings\Filament\SettingsPageDefinition;
use Nhwin\Settings\Tests\Fixtures\TestSettingsPage;

it('keeps registry configuration isolated per plugin instance', function (): void {
    $admin = SettingsPlugin::make()->pages([
        SettingsPageDefinition::make(TestSettingsPage::class)
            ->label('General settings')
            ->description('Application defaults')
            ->group('System')
            ->sort(10),
    ])->hub();

    $author = SettingsPlugin::make()->pages([
        SettingsPageDefinition::external('/profile/settings')
            ->label('Profile settings')
            ->sort(1),
    ]);

    expect($admin->registry()->all())->toHaveCount(1)
        ->and($admin->registry()->all()[0]->getLabel())->toBe('General settings')
        ->and($author->registry()->all())->toHaveCount(1)
        ->and($author->registry()->all()[0]->getLabel())->toBe('Profile settings')
        ->and($author->registry()->pageClasses())->toBe([]);
});

it('filters inaccessible hub destinations', function (): void {
    $plugin = SettingsPlugin::make()->pages([
        SettingsPageDefinition::external('/allowed')->label('Allowed'),
        SettingsPageDefinition::external('/denied')->label('Denied')->canAccess(fn (): bool => false),
    ]);

    expect($plugin->registry()->accessible())->toHaveCount(1)
        ->and($plugin->registry()->accessible()[0]->getLabel())->toBe('Allowed');
});
