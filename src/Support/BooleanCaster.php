<?php

declare(strict_types=1);

namespace Nhwin\Settings\Support;

use Nhwin\Settings\Exceptions\InvalidSettingType;

final class BooleanCaster
{
    private const STRINGS = [
        '1',
        '0',
        'true',
        'false',
        'yes',
        'no',
        'on',
        'off',
    ];

    public static function cast(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, self::STRINGS, true)) {
                $result = filter_var($normalized, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($result !== null) {
                    return $result;
                }
            }
        }

        throw InvalidSettingType::forKey($key, 'boolean', $value);
    }
}
