<?php

use App\Filament\Admin\Pages\PresetGeneralSettings;
use App\Settings\PresetGeneralSettingsDefinition;
use Illuminate\Support\Str;

it('normalizes panel IDs into valid paths and namespaces', function (string $panel, string $namespace): void {
    $name = 'Panel'.Str::studly($panel);

    $this->artisan('make:settings', [
        'name' => $name,
        '--panel' => $panel,
        '--no-interaction' => true,
        '--force' => true,
    ])->assertSuccessful();

    $path = app_path("Filament/{$namespace}/Pages/{$name}Settings.php");

    expect($path)->toBeFile()
        ->and(file_get_contents($path))->toContain("namespace App\\Filament\\{$namespace}\\Pages;")
        ->not->toContain('$PANEL$', '$CLASS_NAME$', '$SETTING_NAME$');
})->with([
    ['admin', 'Admin'],
    ['super-admin', 'SuperAdmin'],
    ['author_panel', 'AuthorPanel'],
]);

it('generates preset fields typed definitions and hub metadata', function (): void {
    $this->artisan('make:settings', [
        'name' => 'PresetGeneral',
        '--panel' => 'admin',
        '--preset' => 'general',
        '--group' => 'company-general',
        '--typed' => true,
        '--hub' => true,
        '--no-interaction' => true,
        '--force' => true,
    ])->assertSuccessful();

    $page = app_path('Filament/Admin/Pages/PresetGeneralSettings.php');
    $definition = app_path('Settings/PresetGeneralSettingsDefinition.php');

    expect($page)->toBeFile()
        ->and($definition)->toBeFile()
        ->and(file_get_contents($page))
        ->toContain("TextInput::make('site_name')")
        ->toContain('settingsPageDefinition()')
        ->toContain("return 'company-general';")
        ->and(file_get_contents($definition))
        ->toContain("return 'company-general';")
        ->toContain("'maintenance' => 'boolean'")
        ->not->toContain('$DEFAULT_DATA$', '$CASTS$', '$ENCRYPTED$');

    require_once $page;
    require_once $definition;

    expect(class_exists(PresetGeneralSettings::class))->toBeTrue()
        ->and(class_exists(PresetGeneralSettingsDefinition::class))->toBeTrue();
});
