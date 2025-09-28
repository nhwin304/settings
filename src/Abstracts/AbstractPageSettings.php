<?php

declare(strict_types=1);

namespace Nhwin\Settings\Abstracts;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;

/**
 * Abstract base page for Filament settings pages that persist a named configuration group.
 *
 * Loads default values, merges them with persisted data from the database, and provides
 * lifecycle helpers and a save routine that persists form state into the corresponding
 * configuration group.
 *
 * @property object|null $form Instance of the content (form/schema) used by the page.
 * @property object|null $content Instance of the content (form/schema) used by the page.
 */
abstract class AbstractPageSettings extends Page
{
    /**
     * Data loaded from the DB config group.
     *
     * @var array<string,mixed>|null
     */
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-8-tooth';

    /**
     * Returns the navigation group label used by the Filament UI to group this page.
     *
     * The value is retrieved from translation resources and may be null when a translation
     * is not defined.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('settings::settings.navigation_group');
    }

    abstract protected function settingName(): string;

    /**
     * Returns the default data used to initialize the page state.
     *
     * These defaults are merged with persisted values; persisted values take precedence.
     *
     * @return array<string, mixed> Array of default values keyed by setting name.
     */
    public function getDefaultData(): array
    {
        return [];
    }

    /**
     * Returns the formatted last-updated timestamp for the settings group associated with this page.
     *
     * Accepts timezone and format parameters and returns a formatted string, or null if the
     * timestamp is not available.
     *
     * @param  string       $format   Date format string compatible with PHP's date() function.
     * @param  string|null  $timezone Optional timezone identifier (e.g. 'UTC', 'Europe/Rome').
     * @return string|null Formatted timestamp or null if not available.
     */
    public function lastUpdatedAt(string $format = 'H:i:s d/m/Y', ?string $timezone = null): ?string
    {
        return Setting::getGroupLastUpdatedAt($this->settingName(), $format, $timezone);
    }

    /**
     * Initializes the page state by loading persisted values for the settings group and merging them with defaults.
     *
     * The merged result is assigned to the internal `$data` property.
     * If the page defines a `$form` or `$content` property, it is filled with the merged data.
     */
    public function mount(): void
    {
        $db = Setting::getGroup($this->settingName()) ?? [];
        $defaults = $this->getDefaultData();

        // Merge defaults with DB values: DB values take precedence.
        $this->data = array_replace_recursive($defaults, $db);

        // Support both $this->content and $this->form for the schema instance.
        if (! isset($this->form)) {
            $this->form = $this->content;
        }

        $this->form->fill($this->data);
    }

    /**
     * Persists the current form state into the associated settings group.
     *
     * If `$this->form` is not set, `$this->content` is used as fallback. The method verifies at runtime
     * that the form instance exposes `getState()`; it iterates every key/value pair returned by `getState()`
     * and calls `Setting::set("{settingName}.{key}", $value)` to persist each value. A Filament
     * notification is sent upon successful completion.
     *
     * @throws RuntimeException When the form instance is missing or does not provide `getState()`.
     */
    public function save(): void
    {
        // Support both $this->content and $this->form for the schema instance.
        if (! isset($this->form)) {
            $this->form = $this->content;
        }

        if (! is_object($this->form) || ! method_exists($this->form, 'getState')) {
            throw new \RuntimeException('Expected $this->form to be an object exposing getState().');
        }

        /** @var array<string,mixed> $state */
        $state = $this->form->getState();

        collect($state)->each(function ($setting, $key) {
            Setting::set($this->settingName() . '.' . $key, $setting);
        });

        Notification::make()
            ->success()
            ->title(__('settings::settings.saved_title'))
            ->body(__('settings::settings.saved_body'))
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('settings::settings.save'))
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
        ];
    }
}