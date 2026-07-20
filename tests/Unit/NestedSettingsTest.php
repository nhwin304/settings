<?php

use Nhwin\Settings\Facades\Setting;

it('preserves nested sibling values through deep updates', function (): void {
    Setting::set('nested.social', ['links' => ['github' => 'old', 'docs' => 'keep']]);
    Setting::set('nested.social.links.github', 'new');

    expect(Setting::get('nested.social.links'))->toBe(['github' => 'new', 'docs' => 'keep']);
});
