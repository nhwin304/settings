<?php

use Nhwin\Settings\Facades\Setting;
use Nhwin\Settings\Tests\Fixtures\FakeSettingsForm;
use Nhwin\Settings\Tests\Fixtures\TestSettingsPage;

it('runs the native fill and bulk save lifecycle', function (): void {
    Setting::set('general.site_name', 'Persisted');

    $page = new TestSettingsPage;
    $page->testForm = new FakeSettingsForm;
    $page->mount();

    expect($page->testForm->state)->toBe([
        'site_name' => 'Persisted',
        'maintenance' => false,
        'filled' => true,
    ])->and($page->hooks)->toBe(['beforeFill', 'afterFill']);

    $page->testForm->state = ['site_name' => 'Saved', 'maintenance' => true];
    $page->save();

    expect(Setting::getGroup('general'))->toMatchArray([
        'site_name' => 'Saved',
        'maintenance' => true,
        'mutated' => true,
    ])->and($page->testForm->relationshipSaves)->toBe(1)
        ->and($page->hooks)->toBe([
            'beforeFill',
            'afterFill',
            'beforeValidate',
            'afterValidate',
            'beforeSave',
            'afterSave',
            'afterCommit',
        ]);
});

it('runs afterCommit outside the transaction with committed settings available', function (): void {
    Setting::set('general.site_name', 'Before');
    $page = new TestSettingsPage;
    $page->testForm = new FakeSettingsForm(['site_name' => 'After']);

    $page->save();

    expect($page->transactionLevels['afterSave'])->toBeGreaterThan(0)
        ->and($page->transactionLevels['afterCommit'])->toBe(0)
        ->and($page->afterCommitSiteName)->toBe('After');
});

it('does not run afterCommit when the page transaction rolls back', function (): void {
    Setting::set('general.site_name', 'Before');
    $page = new TestSettingsPage;
    $page->testForm = new FakeSettingsForm(['site_name' => 'After']);
    $page->failAfterSave = true;

    expect(fn () => $page->save())->toThrow(RuntimeException::class, 'Rollback requested');

    expect($page->hooks)->toContain('afterSave')
        ->not->toContain('afterCommit')
        ->and(Setting::get('general.site_name'))->toBe('Before');
});
