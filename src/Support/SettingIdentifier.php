<?php

declare(strict_types=1);

namespace Nhwin\Settings\Support;

use InvalidArgumentException;

final class SettingIdentifier
{
    public const MAX_DATABASE_LENGTH = 255;

    public const MAX_SETTING_KEY_LENGTH = 1024;

    public static function scope(string $scope): string
    {
        return self::validate($scope, 'scope');
    }

    public static function group(string $group): string
    {
        return self::validate($group, 'group', allowDot: false);
    }

    public static function root(string|int $root): string
    {
        if (! is_string($root)) {
            throw new InvalidArgumentException('Settings root keys must be strings.');
        }

        return self::validate($root, 'root key', allowDot: false);
    }

    public static function nestedSegment(string $segment): string
    {
        return self::validate($segment, 'nested path segment', allowDot: false);
    }

    public static function settingKey(string $key): string
    {
        if (mb_strlen($key) > self::MAX_SETTING_KEY_LENGTH) {
            throw new InvalidArgumentException(
                'A setting key may not exceed '.self::MAX_SETTING_KEY_LENGTH.' characters.',
            );
        }

        return $key;
    }

    private static function validate(string $value, string $label, bool $allowDot = true): string
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("The settings {$label} must not be empty.");
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException("The settings {$label} must not contain control characters.");
        }

        if (mb_strlen($value) > self::MAX_DATABASE_LENGTH) {
            throw new InvalidArgumentException(
                "The settings {$label} may not exceed ".self::MAX_DATABASE_LENGTH.' characters.',
            );
        }

        if (! $allowDot && str_contains($value, '.')) {
            throw new InvalidArgumentException("The settings {$label} must not contain a dot separator.");
        }

        return $value;
    }
}
