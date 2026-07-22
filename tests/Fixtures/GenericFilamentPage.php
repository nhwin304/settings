<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

use Filament\Pages\Page;

final class GenericFilamentPage extends Page
{
    protected string $view = 'settings::filament.pages.settings-hub';
}
