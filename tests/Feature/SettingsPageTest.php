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
        ]);
});
