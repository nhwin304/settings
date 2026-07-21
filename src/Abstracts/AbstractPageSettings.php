<?php

declare(strict_types=1);

namespace Nhwin\Settings\Abstracts;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Nhwin\Settings\Definitions\DefinitionRegistry;
use Nhwin\Settings\Facades\Setting;
use Nhwin\Settings\Filament\Compatibility\SettingsPageAdapter;
use Nhwin\Settings\Filament\Plugins\SettingsPlugin;
use Throwable;

/**
 * @property object $form
 * @property object $content
 */
abstract class AbstractPageSettings extends Page
{
    use CanUseDatabaseTransactions;
    use HasUnsavedDataChangesAlert;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    public static function getNavigationGroup(): ?string
    {
        return __('settings::settings.navigation_group');
    }

    public static function canAccess(): bool
    {
        if (! parent::canAccess() || ! static::canAccessSettingsPage()) {
            return false;
        }

        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasPlugin('settings')) {
            return true;
        }

        $plugin = $panel->getPlugin('settings');

        if (! $plugin instanceof SettingsPlugin) {
            return true;
        }

        if (! $plugin->isAccessible()) {
            return false;
        }

        $definition = $plugin->registry()->findByPage(static::class);

        return $definition === null || $definition->passesAccessCallback();
    }

    protected static function canAccessSettingsPage(): bool
    {
        return true;
    }

    abstract protected function settingName(): string;

    /** @return array<string, mixed> */
    public function getDefaultData(): array
    {
        return [];
    }

    public function lastUpdatedAt(
        string $format = 'H:i:s d/m/Y',
        ?string $timezone = null,
    ): ?string {
        return Setting::getGroupLastUpdatedAt($this->settingName(), $format, $timezone);
    }

    public function mount(): void
    {
        $this->callHook('beforeFill');

        $data = array_replace_recursive(
            $this->getDefaultData(),
            Setting::getGroup($this->settingName()),
        );

        $definition = app(DefinitionRegistry::class)->get($this->settingName());

        if ($definition !== null) {
            foreach ($definition->encrypted() as $path) {
                if (data_get($data, $path) !== null) {
                    data_set($data, $path, '');
                }
            }
        }

        $this->data = $this->mutateFormDataBeforeFill($data);

        $this->adapter()->fill($this->settingsForm(), $this->data);
        $this->callHook('afterFill');
        $this->rememberData();
    }

    public function save(): void
    {
        $form = $this->settingsForm();

        try {
            $this->beginDatabaseTransaction();
            $this->callHook('beforeValidate');

            $data = $this->adapter()->state($form);
            $this->callHook('afterValidate');
            $data = $this->mutateFormDataBeforeSave($data);
            $this->callHook('beforeSave');

            Setting::setMany($this->settingName(), $data);
            $this->adapter()->saveRelationships($form);
            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();
        $this->data = $data;
        $this->rememberData();
        $this->getSavedNotification()?->send();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    protected function settingsForm(): object
    {
        if (isset($this->form)) {
            return $this->form;
        }

        return $this->content;
    }

    protected function adapter(): SettingsPageAdapter
    {
        return app(SettingsPageAdapter::class);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('settings::settings.saved_title'))
            ->body(__('settings::settings.saved_body'));
    }

    /** @return array<Action> */
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
