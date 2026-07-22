<?php

it('keeps Composer README and CI compatibility declarations aligned', function (): void {
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $workflow = file_get_contents(__DIR__.'/../../.github/workflows/tests.yml');
    $readme = file_get_contents(__DIR__.'/../../README.md');

    expect($composer['require'])
        ->toMatchArray([
            'php' => '^8.3',
            'laravel/framework' => '^11.28|^12.0|^13.0',
            'filament/filament' => '^4.0|^5.0',
        ])
        ->and($workflow)->toContain(
            "php: '8.3'",
            "php: '8.4'",
            "laravel: '^11.28'",
            "laravel: '^12.0'",
            "laravel: '^13.0'",
            "filament: '^4.0'",
            "filament: '^5.0'",
        )
        ->and(substr_count($workflow, "          - php: '"))->toBe(14)
        ->and($workflow)->toContain(
            'prefer-lowest:',
            '--prefer-lowest --prefer-stable --with-all-dependencies',
        )
        ->and($workflow)->not->toContain("php: '8.2'")
        ->and($readme)->toContain(
            '| PHP | 8.3+ |',
            'Constraint Composer cho phép PHP 8.3 trở lên.',
            '| Laravel | 11.28+, 12.x hoặc 13.x |',
            '| Filament | 4.x',
        )
        ->not->toContain('PHP 8.1', '| PHP | 8.2');
});
