<?php

namespace Nhwin\Settings\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Nhwin\Settings\Commands\SettingCommand;
use Nhwin\Settings\Contracts\ScopeResolver;
use Nhwin\Settings\Contracts\SettingsManagerContract;
use Nhwin\Settings\Contracts\SettingsRepository;
use Nhwin\Settings\Definitions\DefinitionRegistry;
use Nhwin\Settings\Repositories\DatabaseSettingsRepository;
use Nhwin\Settings\Services\SettingsManager;
use Nhwin\Settings\Support\SettingsCache;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SettingServiceProvider extends PackageServiceProvider
{
    public static string $name = 'settings';

    public static string $viewNamespace = 'settings';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations($this->getMigrations())
            ->hasTranslations()
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('nhwin304/settings');
            });

    }

    public function packageRegistered(): void
    {
        $this->app->singleton(DefinitionRegistry::class, function ($app): DefinitionRegistry {
            $definitions = array_map(
                fn (string $definition): \Nhwin\Settings\Definitions\SettingsDefinition => $app->make($definition),
                $app['config']->get('settings.definitions', []),
            );

            return new DefinitionRegistry($definitions);
        });
        $this->app->bind(ScopeResolver::class, fn ($app): ScopeResolver => $app->make(
            $app['config']->get('settings.scope_resolver'),
        ));
        $this->app->singleton(SettingsRepository::class, DatabaseSettingsRepository::class);
        $this->app->singleton(SettingsCache::class, fn ($app): SettingsCache => new SettingsCache($app['cache']->store()));
        $this->app->singleton(SettingsManagerContract::class, SettingsManager::class);
    }

    public function packageBooted(): void
    {
        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__.'/../../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/settings/{$file->getFilename()}"),
                ], 'settings-stubs');
            }
        }

        // Set the Blade directive to retrieve the settings
        Blade::directive('settings', function ($expression) {
            return "<?php echo e(settings($expression)); ?>";
        });
    }

    protected function getAssetPackageName(): ?string
    {
        return 'nhwin/settings';
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            SettingCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_settings_table',
            'add_scope_to_settings_table',
        ];
    }
}
