<?php

declare(strict_types=1);

namespace Nhwin\Settings\Listeners;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;
use Nhwin\Settings\Contracts\SettingsAuditRecorder;
use Nhwin\Settings\Events\SettingDeleted;
use Nhwin\Settings\Events\SettingsGroupDeleted;
use Nhwin\Settings\Events\SettingsGroupUpdated;
use Nhwin\Settings\Events\SettingUpdated;
use Nhwin\Settings\Support\SettingsAuditEntry;

final class SettingsAuditListener
{
    public function __construct(
        private SettingsAuditRecorder $recorder,
        private Config $config,
    ) {}

    public function handle(
        SettingUpdated|SettingsGroupUpdated|SettingDeleted|SettingsGroupDeleted $event,
    ): void {
        if (! $this->shouldRecord($event)) {
            return;
        }

        [$key, $action, $oldValue, $newValue] = match (true) {
            $event instanceof SettingUpdated => [
                $event->key,
                'updated',
                $event->oldValue,
                $event->newValue,
            ],
            $event instanceof SettingsGroupUpdated => [
                null,
                'group_updated',
                $event->oldValues,
                $event->newValues,
            ],
            $event instanceof SettingDeleted => [
                $event->key,
                'deleted',
                $event->oldValue,
                null,
            ],
            $event instanceof SettingsGroupDeleted => [
                null,
                'group_deleted',
                $event->oldValues,
                null,
            ],
        };

        $this->recorder->record(new SettingsAuditEntry(
            actorId: null,
            actorType: null,
            scope: $event->scope,
            group: $event->group,
            key: $key,
            action: $action,
            oldValue: $oldValue,
            newValue: $newValue,
            createdAt: new DateTimeImmutable,
        ));
    }

    private function shouldRecord(
        SettingUpdated|SettingsGroupUpdated|SettingDeleted|SettingsGroupDeleted $event,
    ): bool {
        $granularity = $this->config->get('settings.audit.granularity', 'setting');

        if (! is_string($granularity) || ! in_array($granularity, ['setting', 'group', 'both'], true)) {
            throw new InvalidArgumentException(
                'The settings.audit.granularity configuration must be setting, group, or both.',
            );
        }

        return match ($granularity) {
            'setting' => $event instanceof SettingUpdated || $event instanceof SettingDeleted,
            'group' => $event instanceof SettingsGroupUpdated || $event instanceof SettingsGroupDeleted,
            'both' => true,
        };
    }
}
