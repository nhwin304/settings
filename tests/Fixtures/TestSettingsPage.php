<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Nhwin\Settings\Abstracts\AbstractPageSettings;
use Nhwin\Settings\Facades\Setting;
use RuntimeException;

final class TestSettingsPage extends AbstractPageSettings
{
    protected string $view = 'settings::filament.pages.settings-hub';

    public FakeSettingsForm $testForm;

    /** @var list<string> */
    public array $hooks = [];

    /** @var array<string, int> */
    public array $transactionLevels = [];

    public ?string $afterCommitSiteName = null;

    public bool $failAfterSave = false;

    protected function settingName(): string
    {
        return 'general';
    }

    public function getDefaultData(): array
    {
        return ['site_name' => 'Default', 'maintenance' => false];
    }

    protected function settingsForm(): object
    {
        return $this->testForm;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['filled'] = true;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['mutated'] = true;

        return $data;
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    protected function beforeFill(): void
    {
        $this->hooks[] = 'beforeFill';
    }

    protected function afterFill(): void
    {
        $this->hooks[] = 'afterFill';
    }

    protected function beforeValidate(): void
    {
        $this->hooks[] = 'beforeValidate';
    }

    protected function afterValidate(): void
    {
        $this->hooks[] = 'afterValidate';
    }

    protected function beforeSave(): void
    {
        $this->hooks[] = 'beforeSave';
    }

    protected function afterSave(): void
    {
        $this->hooks[] = 'afterSave';
        $this->transactionLevels['afterSave'] = DB::transactionLevel();

        if ($this->failAfterSave) {
            throw new RuntimeException('Rollback requested by the test page.');
        }
    }

    protected function afterCommit(): void
    {
        $this->hooks[] = 'afterCommit';
        $this->transactionLevels['afterCommit'] = DB::transactionLevel();
        $this->afterCommitSiteName = Setting::string('general.site_name');
    }
}
