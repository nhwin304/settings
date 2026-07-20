<?php

declare(strict_types=1);

namespace Nhwin\Settings\Support;

use InvalidArgumentException;

final readonly class SettingKey
{
    public function __construct(
        public string $group,
        public string $root,
        public ?string $nestedPath = null,
    ) {}

    public static function parse(string $key): self
    {
        $segments = explode('.', trim($key));

        if (count($segments) < 2 || in_array('', $segments, true)) {
            throw new InvalidArgumentException(
                "A setting key must contain a non-empty group and key, for example 'general.site_name'.",
            );
        }

        $group = array_shift($segments);
        $root = array_shift($segments);

        return new self($group, $root, $segments === [] ? null : implode('.', $segments));
    }
}
