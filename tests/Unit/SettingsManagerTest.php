<?php

use Nhwin\Settings\Contracts\SettingsManagerContract;

it('resolves the replaceable manager contract from the helper', function (): void {
    expect(settings())->toBeInstanceOf(SettingsManagerContract::class);
});
