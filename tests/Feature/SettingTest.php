<?php

use App\Filament\SuperAdmin\Pages\GeneralSettings;
use Illuminate\Support\Facades\Blade;
use Nhwin\Settings\Abstracts\AbstractPageSettings;
use Nhwin\Settings\Facades\Setting;

it('resolves the facade and retains the helper API', function (): void {
    Setting::set('general.site_name', 'Nhwin');

    expect(Setting::get('general.site_name'))->toBe('Nhwin')
        ->and(settings('general.site_name'))->toBe('Nhwin')
        ->and(settings('general.missing', 'Default'))->toBe('Default');
});

it('renders the Blade directive', function (): void {
    Setting::set('general.site_name', '<Nhwin>');

    expect(Blade::render("@settings('general.site_name', 'Default')"))
        ->toBe('&lt;Nhwin&gt;');
});

it('generates a page with valid namespaces that can be loaded', function (): void {
    $this->artisan('make:settings', [
        'name' => 'General',
        'panel' => 'super-admin',
        '--no-interaction' => true,
        '--force' => true,
    ])->assertSuccessful();

    $path = app_path('Filament/SuperAdmin/Pages/GeneralSettings.php');

    expect($path)->toBeFile()
        ->and(file_get_contents($path))
        ->toContain('namespace App\\Filament\\SuperAdmin\\Pages;')
        ->toContain('use Nhwin\\Settings\\Abstracts\\AbstractPageSettings;');

    require_once $path;

    expect(is_subclass_of(
        GeneralSettings::class,
        AbstractPageSettings::class,
    ))->toBeTrue();
});

it('fails non-interactively without a name and without a type error', function (): void {
    $this->artisan('make:settings', ['--no-interaction' => true])
        ->expectsOutput('The name argument is required when using --no-interaction.')
        ->assertFailed();
});
