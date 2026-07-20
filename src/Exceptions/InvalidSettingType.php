<?php

declare(strict_types=1);

namespace Nhwin\Settings\Exceptions;

use UnexpectedValueException;

final class InvalidSettingType extends UnexpectedValueException
{
    public static function forKey(string $key, string $expected, mixed $actual): self
    {
        return new self(sprintf(
            "Setting '%s' must be %s; %s was stored.",
            $key,
            $expected,
            get_debug_type($actual),
        ));
    }
}
