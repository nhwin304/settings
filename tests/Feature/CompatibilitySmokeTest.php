<?php

use Composer\InstalledVersions;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Nhwin\Settings\Abstracts\AbstractPageSettings;

it('boots against the installed supported Filament major', function (): void {
    $version = InstalledVersions::getPrettyVersion('filament/filament') ?? '0';
    $major = (int) ltrim(explode('.', $version)[0], 'v');

    expect($major)->toBeIn([4, 5])
        ->and(trait_exists(CanUseDatabaseTransactions::class))->toBeTrue()
        ->and(trait_exists(HasUnsavedDataChangesAlert::class))->toBeTrue()
        ->and(class_exists(AbstractPageSettings::class))->toBeTrue();
});
