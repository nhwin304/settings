<?php

declare(strict_types=1);

namespace Nhwin\Settings\Presets;

use InvalidArgumentException;

final class PresetRegistry
{
    /** @return list<string> */
    public function names(): array
    {
        return ['general', 'seo', 'mail', 'social', 'analytics'];
    }

    public function get(string $name): SettingsPreset
    {
        return match ($name) {
            'general' => new SettingsPreset(
                'general',
                'Application identity and defaults',
                [
                    ['type' => 'TextInput', 'name' => 'site_name', 'label' => 'Site name'],
                    ['type' => 'Toggle', 'name' => 'maintenance', 'label' => 'Maintenance mode'],
                ],
                ['site_name' => '', 'maintenance' => false],
                ['site_name' => 'string', 'maintenance' => 'boolean'],
            ),
            'seo' => new SettingsPreset(
                'seo',
                'Search engine metadata defaults',
                [
                    ['type' => 'TextInput', 'name' => 'title', 'label' => 'Default title'],
                    ['type' => 'Textarea', 'name' => 'description', 'label' => 'Default description'],
                ],
                ['title' => '', 'description' => ''],
                ['title' => 'string', 'description' => 'string'],
            ),
            'mail' => new SettingsPreset(
                'mail',
                'Outgoing mail transport settings',
                [
                    ['type' => 'TextInput', 'name' => 'smtp.host', 'label' => 'SMTP host'],
                    ['type' => 'TextInput', 'name' => 'smtp.username', 'label' => 'SMTP username'],
                    ['type' => 'TextInput', 'name' => 'smtp.password', 'label' => 'SMTP password'],
                ],
                ['smtp' => ['host' => '', 'username' => '', 'password' => '']],
                ['smtp.host' => 'string', 'smtp.username' => 'string', 'smtp.password' => 'string'],
                ['smtp.password'],
            ),
            'social' => new SettingsPreset(
                'social',
                'Social profile links',
                [
                    ['type' => 'TextInput', 'name' => 'facebook', 'label' => 'Facebook URL'],
                    ['type' => 'TextInput', 'name' => 'github', 'label' => 'GitHub URL'],
                ],
                ['facebook' => '', 'github' => ''],
                ['facebook' => 'string', 'github' => 'string'],
            ),
            'analytics' => new SettingsPreset(
                'analytics',
                'Analytics provider configuration',
                [
                    ['type' => 'TextInput', 'name' => 'provider', 'label' => 'Provider'],
                    ['type' => 'TextInput', 'name' => 'tracking_id', 'label' => 'Tracking ID'],
                ],
                ['provider' => '', 'tracking_id' => ''],
                ['provider' => 'string', 'tracking_id' => 'string'],
            ),
            default => throw new InvalidArgumentException("Unknown settings preset '{$name}'."),
        };
    }
}
