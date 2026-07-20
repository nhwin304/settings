<?php

declare(strict_types=1);

namespace Nhwin\Settings\Filament;

use Filament\Pages\Page;

final class SettingsRegistry
{
    /** @var list<SettingsPageDefinition> */
    private array $definitions = [];

    /** @param array<class-string<Page>|SettingsPageDefinition> $pages */
    public function add(array $pages): void
    {
        foreach ($pages as $page) {
            if (is_string($page) && method_exists($page, 'settingsPageDefinition')) {
                $definition = $page::settingsPageDefinition();

                if ($definition instanceof SettingsPageDefinition) {
                    $this->definitions[] = $definition;

                    continue;
                }
            }

            $this->definitions[] = $page instanceof SettingsPageDefinition
                ? $page
                : SettingsPageDefinition::make($page);
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

    /** @return list<class-string<Page>> */
    public function pageClasses(): array
    {
        return array_values(array_filter(array_map(
            fn (SettingsPageDefinition $definition): ?string => $definition->pageClass(),
            $this->definitions,
        )));
    }
}
