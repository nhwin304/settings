<?php

use Filament\Facades\Filament;
use Filament\FilamentManager;
use Filament\Panel;
use Nhwin\Settings\Filament\Plugins\SettingsPlugin;
use Nhwin\Settings\Filament\SettingsPageDefinition;
use Nhwin\Settings\Tests\Fixtures\GenericFilamentPage;
use Nhwin\Settings\Tests\Fixtures\OtherSettingsPage;
use Nhwin\Settings\Tests\Fixtures\RestrictedSettingsPage;
use Nhwin\Settings\Tests\Fixtures\TestSettingsPage;

beforeEach(function (): void {
    app()->instance('filament', new FilamentManager);
    Filament::clearResolvedInstance('filament');
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
    Filament::clearResolvedInstance('filament');
});

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

it('rejects generic Filament pages from protected internal registration', function (): void {
    expect(fn () => SettingsPlugin::make()->pages([GenericFilamentPage::class]))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

it('blocks every settings page when plugin access is denied', function (): void {
    $plugin = SettingsPlugin::make()
        ->pages([TestSettingsPage::class, OtherSettingsPage::class])
        ->canAccess(fn (): bool => false);
    $panel = Panel::make()->id('admin')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    expect(TestSettingsPage::canAccess())->toBeFalse()
        ->and(OtherSettingsPage::canAccess())->toBeFalse()
        ->and($plugin->registry()->accessible())->toBe([]);
});

it('hides and directly blocks a page denied by its definition', function (): void {
    $plugin = SettingsPlugin::make()->pages([
        SettingsPageDefinition::make(TestSettingsPage::class)
            ->canAccess(fn (): bool => false),
    ]);
    $panel = Panel::make()->id('admin')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    expect($plugin->registry()->accessible())->toBe([])
        ->and(TestSettingsPage::canAccess())->toBeFalse();
});

it('allows a page when plugin and definition access are granted', function (): void {
    $plugin = SettingsPlugin::make()
        ->pages([
            SettingsPageDefinition::make(TestSettingsPage::class)
                ->canAccess(fn (): bool => true),
        ])
        ->canAccess(fn (): bool => true);
    $panel = Panel::make()->id('admin')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    expect(TestSettingsPage::canAccess())->toBeTrue()
        ->and($plugin->registry()->accessible())->toHaveCount(1);
});

it('applies independent definition rules without recursion', function (): void {
    $plugin = SettingsPlugin::make()->pages([
        SettingsPageDefinition::make(TestSettingsPage::class)
            ->canAccess(fn (): bool => false),
        SettingsPageDefinition::make(OtherSettingsPage::class)
            ->canAccess(fn (): bool => true),
    ]);
    $panel = Panel::make()->id('admin')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    expect(TestSettingsPage::canAccess())->toBeFalse()
        ->and(OtherSettingsPage::canAccess())->toBeTrue()
        ->and($plugin->registry()->accessible())->toHaveCount(1)
        ->and($plugin->registry()->accessible()[0]->pageClass())->toBe(OtherSettingsPage::class);
});

it('keeps definition access isolated between current panels', function (): void {
    $admin = Panel::make()->id('admin')->plugin(
        SettingsPlugin::make()->pages([
            SettingsPageDefinition::make(TestSettingsPage::class)
                ->canAccess(fn (): bool => true),
        ]),
    );
    $author = Panel::make()->id('author')->plugin(
        SettingsPlugin::make()->pages([
            SettingsPageDefinition::make(TestSettingsPage::class)
                ->canAccess(fn (): bool => false),
        ]),
    );

    Filament::setCurrentPanel($admin);
    expect(TestSettingsPage::canAccess())->toBeTrue();

    Filament::setCurrentPanel($author);
    expect(TestSettingsPage::canAccess())->toBeFalse();
});

it('replaces duplicate page registrations with the latest definition', function (): void {
    $plugin = SettingsPlugin::make()->pages([
        SettingsPageDefinition::make(TestSettingsPage::class)
            ->label('First')
            ->sort(20),
        SettingsPageDefinition::external('/external')->label('External'),
        SettingsPageDefinition::make(TestSettingsPage::class)
            ->label('Latest')
            ->sort(10),
    ]);
    $registry = $plugin->registry();

    expect($registry->all())->toHaveCount(2)
        ->and($registry->findByPage(TestSettingsPage::class))->not->toBeNull()
        ->and($registry->findByPage(TestSettingsPage::class)?->getLabel())->toBe('Latest')
        ->and($registry->pageClasses())->toBe([TestSettingsPage::class]);
});

it('combines native page access with plugin and definition access', function (): void {
    $plugin = SettingsPlugin::make()->pages([
        SettingsPageDefinition::make(RestrictedSettingsPage::class)
            ->canAccess(fn (): bool => true),
    ]);
    Filament::setCurrentPanel(Panel::make()->id('admin')->plugin($plugin));

    expect(RestrictedSettingsPage::canAccess())->toBeFalse()
        ->and($plugin->registry()->accessible())->toBe([]);
});
