<?php

declare(strict_types=1);

namespace Nhwin\Settings\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nhwin\Settings\Presets\PresetRegistry;
use Nhwin\Settings\Presets\SettingsPreset;

use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

class SettingCommand extends Command
{
    protected $signature = 'make:settings
        {name? : Settings page name}
        {panel? : Backward-compatible panel ID}
        {--panel= : Filament panel ID}
        {--group= : Stored settings group}
        {--preset= : Preset: general, seo, mail, social, analytics}
        {--hub : Generate Settings Hub metadata}
        {--typed : Generate a SettingsDefinition class}
        {--force : Overwrite existing files}';

    protected $description = 'Create a Filament settings page and its Blade view.';

    /** @var list<string> */
    protected $aliases = ['settings'];

    protected ?string $cachedName = null;

    protected ?string $cachedPanel = null;

    protected bool $panelCached = false;

    public function __construct(
        protected Filesystem $files,
        protected PresetRegistry $presets,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->argument('name') && $this->option('no-interaction')) {
            $this->error('The name argument is required when using --no-interaction.');

            return self::FAILURE;
        }

        $presetName = $this->option('preset');

        if (is_string($presetName) && ! in_array($presetName, $this->presets->names(), true)) {
            $this->error("Unknown preset '{$presetName}'. Available presets: ".implode(', ', $this->presets->names()).'.');

            return self::FAILURE;
        }

        $path = $this->getSourceFilePath();
        $contents = $this->getSourceFile();

        if ($contents === false) {
            $this->error("Unable to build the settings page at {$path}.");

            return self::FAILURE;
        }

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->warn("File: {$path} already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $this->makeDirectory(dirname($path));
        $this->files->put($path, $contents);

        $viewName = 'filament.pages.'.Str::kebab($this->getNameArgument()).'-settings';
        $this->createViewFromStub($viewName);

        if ($this->option('typed')) {
            $this->createDefinitionFromStub();
        }

        $this->info("File: {$path} created.");

        return self::SUCCESS;
    }

    protected function getNameArgument(): string
    {
        if ($this->cachedName !== null) {
            return $this->cachedName;
        }

        $name = $this->argument('name');

        if (! $name) {
            if ($this->option('no-interaction')) {
                throw new InvalidArgumentException('The name argument is required when using --no-interaction.');
            }

            $name = text(
                label: 'What is the name of the settings page?',
                placeholder: 'E.g. Site, General, Mail',
                required: true,
            );
        }

        $name = (string) Str::of((string) $name)->replaceMatches('/settings$/i', '')->trim();

        if ($name === '') {
            throw new InvalidArgumentException('The settings page name cannot be empty.');
        }

        return $this->cachedName = $name;
    }

    protected function getPanelArgument(): ?string
    {
        if ($this->panelCached) {
            return $this->cachedPanel;
        }

        $panel = $this->option('panel') ?: $this->argument('panel');

        if (! $panel && ! $this->option('no-interaction')) {
            $panels = $this->getAvailablePanels();

            if (count($panels) > 1) {
                $panel = suggest(
                    label: 'Which Filament panel should contain this page?',
                    options: $panels,
                    placeholder: 'Leave empty for the default panel',
                ) ?: null;
            }
        }

        $this->panelCached = true;

        return $this->cachedPanel = is_string($panel) && $panel !== '' ? $panel : null;
    }

    /** @return list<string> */
    protected function getAvailablePanels(): array
    {
        return array_keys(Filament::getPanels());
    }

    public function createViewFromStub(string $viewName): void
    {
        $path = resource_path('views/'.str_replace('.', '/', $viewName).'.blade.php');

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->warn("File: {$path} already exists. Use --force to overwrite it.");

            return;
        }

        $contents = file_get_contents(__DIR__.'/../../stubs/view.stub');

        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read the settings view stub.');
        }

        $this->makeDirectory(dirname($path));
        $this->files->put($path, str_replace('$VIEW_NAME$', $viewName, $contents));
        $this->info("View file: {$path} created.");
    }

    public function getStubPath(): string
    {
        return __DIR__.'/../../stubs/page.stub';
    }

    /** @return array<string, string> */
    public function getStubVariables(): array
    {
        $name = $this->getNameArgument();
        $panel = $this->getPanelArgument();
        $preset = $this->getPreset();
        $className = $this->getSingularClassName($name);

        return [
            'TITLE' => Str::headline($className),
            'PANEL' => $panel ? Str::studly($panel).'\\' : '',
            'CLASS_NAME' => $className,
            'SETTING_NAME' => $this->getSettingName(),
            'COMPONENT_IMPORTS' => $this->componentImports($preset),
            'FORM_COMPONENTS' => $this->formComponents($preset),
            'DEFAULT_DATA' => $this->export($preset === null ? [] : $preset->defaults),
            'HUB_IMPORT' => $this->option('hub') ? 'use Nhwin\\Settings\\Filament\\SettingsPageDefinition;' : '',
            'HUB_METADATA' => $this->hubMetadata($preset),
        ];
    }

    public function getSourceFile(): string|false
    {
        return $this->getStubContents($this->getStubPath(), $this->getStubVariables());
    }

    /** @param array<string, string> $variables */
    public function getStubContents(string $stub, array $variables = []): string|false
    {
        $contents = file_get_contents($stub);

        if ($contents === false) {
            return false;
        }

        foreach ($variables as $search => $replace) {
            $contents = str_replace('$'.$search.'$', $replace, $contents);
        }

        return $contents;
    }

    public function getSourceFilePath(): string
    {
        $panel = $this->getPanelArgument();
        $segments = [app_path('Filament')];

        if ($panel) {
            $segments[] = Str::studly($panel);
        }

        $segments[] = 'Pages';
        $segments[] = $this->getSingularClassName($this->getNameArgument()).'Settings.php';

        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    public function getSingularClassName(string $name): string
    {
        return Str::studly(Pluralizer::singular($name));
    }

    protected function getSettingName(): string
    {
        $group = $this->option('group');

        return is_string($group) && $group !== ''
            ? Str::kebab($group)
            : (($preset = $this->getPreset()) === null ? Str::kebab($this->getNameArgument()) : $preset->name);
    }

    protected function getPreset(): ?SettingsPreset
    {
        $name = $this->option('preset');

        return is_string($name) && $name !== '' ? $this->presets->get($name) : null;
    }

    protected function componentImports(?SettingsPreset $preset): string
    {
        if ($preset === null) {
            return '';
        }

        $types = array_values(array_unique(array_column($preset->fields, 'type')));

        return implode("\n", array_map(
            fn (string $type): string => "use Filament\\Forms\\Components\\{$type};",
            $types,
        ));
    }

    protected function formComponents(?SettingsPreset $preset): string
    {
        if ($preset === null) {
            return '                //';
        }

        return implode("\n", array_map(function (array $field) use ($preset): string {
            $component = sprintf(
                "                %s::make('%s')->label('%s')",
                $field['type'],
                $field['name'],
                addslashes($field['label']),
            );

            if (in_array($field['name'], $preset->encrypted, true)) {
                $component .= '->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state))';
            }

            return $component.',';
        }, $preset->fields));
    }

    protected function hubMetadata(?SettingsPreset $preset): string
    {
        if (! $this->option('hub')) {
            return '';
        }

        $description = addslashes($preset === null ? 'Application settings' : $preset->description);

        return <<<PHP
    public static function settingsPageDefinition(): SettingsPageDefinition
    {
        return SettingsPageDefinition::make(self::class)
            ->label(static::getNavigationLabel())
            ->description('{$description}');
    }
PHP;
    }

    protected function createDefinitionFromStub(): void
    {
        $preset = $this->getPreset();
        $path = app_path('Settings/'.$this->getSingularClassName($this->getNameArgument()).'SettingsDefinition.php');
        $contents = $this->getStubContents(__DIR__.'/../../stubs/definition.stub', [
            'CLASS_NAME' => $this->getSingularClassName($this->getNameArgument()),
            'SETTING_NAME' => $this->getSettingName(),
            'DEFAULT_DATA' => $this->export($preset === null ? [] : $preset->defaults),
            'CASTS' => $this->export($preset === null ? [] : $preset->casts),
            'ENCRYPTED' => $this->export($preset === null ? [] : $preset->encrypted),
        ]);

        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read the settings definition stub.');
        }

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->warn("File: {$path} already exists. Use --force to overwrite it.");

            return;
        }

        $this->makeDirectory(dirname($path));
        $this->files->put($path, $contents);
        $this->info("Definition file: {$path} created.");
    }

    /** @param array<mixed> $value */
    protected function export(array $value): string
    {
        return var_export($value, true);
    }

    protected function makeDirectory(string $path): string
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        return $path;
    }
}
