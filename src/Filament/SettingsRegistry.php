<?php

declare(strict_types=1);

namespace Nhwin\Settings\Filament;

use Nhwin\Settings\Abstracts\AbstractPageSettings;

final class SettingsRegistry
{
    /** @var list<SettingsPageDefinition> */
    private array $definitions = [];

    /** @param array<class-string<AbstractPageSettings>|SettingsPageDefinition> $pages */
    public function add(array $pages): void
    {
        foreach ($pages as $page) {
            if (is_string($page) && method_exists($page, 'settingsPageDefinition')) {
                $definition = $page::settingsPageDefinition();

                if ($definition instanceof SettingsPageDefinition) {
                    $this->addDefinition($definition);

                    continue;
                }
            }

            $this->addDefinition(
                $page instanceof SettingsPageDefinition
                    ? $page
                    : SettingsPageDefinition::make($page),
            );
        }

        usort(
            $this->definitions,
            fn (SettingsPageDefinition $left, SettingsPageDefinition $right): int => $left->getSort() <=> $right->getSort(),
        );
    }

    /** @return list<SettingsPageDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<SettingsPageDefinition> */
    public function accessible(): array
    {
        return array_values(array_filter(
            $this->definitions,
            fn (SettingsPageDefinition $definition): bool => $definition->isAccessible(),
        ));
    }

    /** @param class-string<AbstractPageSettings> $pageClass */
    public function findByPage(string $pageClass): ?SettingsPageDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->pageClass() === $pageClass) {
                return $definition;
            }
        }

        return null;
    }

    /** @return list<class-string<AbstractPageSettings>> */
    public function pageClasses(): array
    {
        return array_values(array_filter(array_map(
            fn (SettingsPageDefinition $definition): ?string => $definition->pageClass(),
            $this->definitions,
        )));
    }

    private function addDefinition(SettingsPageDefinition $definition): void
    {
        $pageClass = $definition->pageClass();

        if ($pageClass !== null) {
            $this->definitions = array_values(array_filter(
                $this->definitions,
                fn (SettingsPageDefinition $registered): bool => $registered->pageClass() !== $pageClass,
            ));
        }

        $this->definitions[] = $definition;
    }
}
