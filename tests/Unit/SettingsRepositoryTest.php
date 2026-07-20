<?php

use Nhwin\Settings\Contracts\SettingsRepository;

it('persists and reads a complete group through the repository contract', function (): void {
    $repository = app(SettingsRepository::class);
    $repository->setMany('global', 'repository', ['name' => 'Nhwin', 'enabled' => true]);

    expect($repository->getGroup('global', 'repository'))->toMatchArray([
        'name' => 'Nhwin',
        'enabled' => true,
    ])->and($repository->lastUpdatedAt('global', 'repository'))->not->toBeNull();
});
