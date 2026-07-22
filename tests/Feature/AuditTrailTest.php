<?php

use Nhwin\Settings\Contracts\SettingsAuditRecorder;
use Nhwin\Settings\Facades\Setting;
use Nhwin\Settings\Support\NullSettingsAuditRecorder;
use Nhwin\Settings\Support\SettingsAuditEntry;

it('uses a no-op audit recorder by default', function (): void {
    expect(app(SettingsAuditRecorder::class))->toBeInstanceOf(NullSettingsAuditRecorder::class);

    Setting::set('general.site_name', 'Nhwin');

    expect(Setting::get('general.site_name'))->toBe('Nhwin');
});

it('records replaceable audit entries from safe domain events', function (): void {
    $recorder = new class implements SettingsAuditRecorder
    {
        /** @var list<SettingsAuditEntry> */
        public array $entries = [];

        public function record(SettingsAuditEntry $entry): void
        {
            $this->entries[] = $entry;
        }
    };
    app()->instance(SettingsAuditRecorder::class, $recorder);

    Setting::setEncrypted('mail.smtp.password', 'audit-secret');
    Setting::forget('mail.smtp.password');

    expect(array_column($recorder->entries, 'action'))->toBe([
        'updated',
        'deleted',
    ])->and($recorder->entries[0]->newValue)->toBe(['password' => '[encrypted]'])
        ->and($recorder->entries[1]->oldValue)->toBe('[encrypted]')
        ->and(serialize($recorder->entries))->not->toContain('audit-secret');
});

it('records group deletion without exposing encrypted values', function (): void {
    config()->set('settings.audit.granularity', 'group');

    $recorder = new class implements SettingsAuditRecorder
    {
        /** @var list<SettingsAuditEntry> */
        public array $entries = [];

        public function record(SettingsAuditEntry $entry): void
        {
            $this->entries[] = $entry;
        }
    };
    app()->instance(SettingsAuditRecorder::class, $recorder);

    Setting::setEncrypted('mail.password', 'group-audit-secret');
    Setting::forgetGroup('mail');

    $entry = $recorder->entries[array_key_last($recorder->entries)];

    expect($entry->action)->toBe('group_deleted')
        ->and($entry->oldValue)->toBe(['password' => '[encrypted]'])
        ->and(serialize($entry))->not->toContain('group-audit-secret');
});

it('records only group events in group granularity mode', function (): void {
    config()->set('settings.audit.granularity', 'group');

    $recorder = new class implements SettingsAuditRecorder
    {
        /** @var list<SettingsAuditEntry> */
        public array $entries = [];

        public function record(SettingsAuditEntry $entry): void
        {
            $this->entries[] = $entry;
        }
    };
    app()->instance(SettingsAuditRecorder::class, $recorder);

    Setting::setMany('general', ['site_name' => 'Nhwin', 'locale' => 'vi']);

    expect(array_column($recorder->entries, 'action'))->toBe(['group_updated']);
});

it('records setting and group events in both granularity mode', function (): void {
    config()->set('settings.audit.granularity', 'both');

    $recorder = new class implements SettingsAuditRecorder
    {
        /** @var list<SettingsAuditEntry> */
        public array $entries = [];

        public function record(SettingsAuditEntry $entry): void
        {
            $this->entries[] = $entry;
        }
    };
    app()->instance(SettingsAuditRecorder::class, $recorder);

    Setting::setMany('general', ['site_name' => 'Nhwin', 'locale' => 'vi']);

    expect(array_column($recorder->entries, 'action'))->toBe([
        'updated',
        'updated',
        'group_updated',
    ]);
});

it('recursively redacts audit values while retaining safe context', function (): void {
    $recorder = new class implements SettingsAuditRecorder
    {
        /** @var list<SettingsAuditEntry> */
        public array $entries = [];

        public function record(SettingsAuditEntry $entry): void
        {
            $this->entries[] = $entry;
        }
    };
    app()->instance(SettingsAuditRecorder::class, $recorder);

    Setting::set('mail.smtp.host', 'smtp.example.test');
    Setting::setEncrypted('mail.smtp.password', 'audit-recursive-secret');

    $entry = $recorder->entries[array_key_last($recorder->entries)];

    expect($entry->newValue)->toBe([
        'host' => 'smtp.example.test',
        'password' => '[encrypted]',
    ])->and(serialize($entry))->not->toContain('audit-recursive-secret');
});
