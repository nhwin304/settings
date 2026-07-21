<?php

declare(strict_types=1);

namespace Nhwin\Settings\Filament;

use Closure;
use Filament\Pages\Page;

final class SettingsPageDefinition
{
    /** @var class-string<Page>|null */
    private ?string $pageClass;

    private ?string $label = null;

    private ?string $description = null;

    private ?string $icon = null;

    private ?string $group = null;

    private int $sort = 0;

    private string|Closure|null $badge = null;

    private ?string $url = null;

    private ?Closure $access = null;

    /** @param class-string<Page>|null $pageClass */
    private function __construct(?string $pageClass)
    {
        $this->pageClass = $pageClass;
    }

    /** @param class-string<Page> $pageClass */
    public static function make(string $pageClass): self
    {
        return new self($pageClass);
    }

    public static function external(string $url): self
    {
        return (new self(null))->url($url);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function group(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function badge(string|Closure|null $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function canAccess(Closure $callback): self
    {
        $this->access = $callback;

        return $this;
    }

    /** @return class-string<Page>|null */
    public function pageClass(): ?string
    {
        return $this->pageClass;
    }

    public function getLabel(): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        return $this->pageClass ? $this->pageClass::getNavigationLabel() : 'Settings';
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function getBadge(): ?string
    {
        $badge = $this->badge;

        return $badge instanceof Closure ? $badge() : $badge;
    }

    public function getUrl(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        if ($this->pageClass === null) {
            return '#';
        }

        return $this->pageClass::getUrl();
    }

    public function isAccessible(): bool
    {
        return $this->passesAccessCallback()
            && ($this->pageClass === null || $this->pageClass::canAccess());
    }

    public function passesAccessCallback(): bool
    {
        return $this->access === null || (bool) ($this->access)();
    }
}
