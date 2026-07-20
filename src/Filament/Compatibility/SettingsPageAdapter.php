<?php

declare(strict_types=1);

namespace Nhwin\Settings\Filament\Compatibility;

use RuntimeException;

final class SettingsPageAdapter
{
    /** @param array<string, mixed> $data */
    public function fill(object $form, array $data): void
    {
        if (! method_exists($form, 'fill')) {
            throw new RuntimeException('The Filament settings schema must expose fill().');
        }

        $form->fill($data);
    }

    /** @return array<string, mixed> */
    public function state(object $form): array
    {
        if (! method_exists($form, 'getState')) {
            throw new RuntimeException('The Filament settings schema must expose getState().');
        }

        $state = $form->getState();

        if (! is_array($state)) {
            throw new RuntimeException('The Filament settings schema state must be an array.');
        }

        return $state;
    }

    public function saveRelationships(object $form): void
    {
        if (method_exists($form, 'saveRelationships')) {
            $form->saveRelationships();
        }
    }
}
