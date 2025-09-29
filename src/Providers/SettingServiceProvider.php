<?php

namespace Nhwin\Settings\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Nhwin\Settings\Commands\SettingCommand;
use Nhwin\Settings\Testing\TestsDbConfig;
use Livewire\Features\SupportTesting\Testable;
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

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/settings/{$file->getFilename()}"),
                ], 'settings-stubs');
            }
        }

        // Set the Blade directive to retrieve the settings
        Blade::directive('settings', function ($expression) {
            return "<?php echo \Nhwin\Settings\Supports\Setting::get($expression); ?>";
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
        ];
    }
}