<?php

declare(strict_types=1);

namespace Nhwin\Settings\Filament\Plugins;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Pages\Page;
use Filament\Panel;
use Nhwin\Settings\Filament\Pages\SettingsHub;
use Nhwin\Settings\Filament\SettingsPageDefinition;
use Nhwin\Settings\Filament\SettingsRegistry;

class SettingsPlugin implements Plugin
{
    protected SettingsRegistry $registry;

    protected bool $hasHub = false;

    protected ?Closure $access = null;

    final public function __construct()
    {
        $this->registry = new SettingsRegistry;
    }

    public static function make(): static
    {
        return new static;
    }

    public static function get(): static
    {
        /** @var static */
        return filament('settings');
    }

    public function getId(): string
    {
        return 'settings';
    }

    /** @param array<class-string<Page>|SettingsPageDefinition> $pages */
    public function pages(array $pages): static
    {
        $this->registry->add($pages);

        return $this;
    }

    public function hub(bool $condition = true): static
    {
        $this->hasHub = $condition;

        return $this;
    }

    public function canAccess(Closure $callback): static
    {
        $this->access = $callback;

        return $this;
    }

    public function isAccessible(): bool
    {
        return $this->access === null || (bool) ($this->access)();
    }

    public function registry(): SettingsRegistry
    {
        return $this->registry;
    }

    public function register(Panel $panel): void
    {
        $pages = $this->registry->pageClasses();

        if ($this->hasHub) {
            $pages[] = SettingsHub::class;
        }

        $panel->pages($pages);
    }

    public function boot(Panel $panel): void {}
}
