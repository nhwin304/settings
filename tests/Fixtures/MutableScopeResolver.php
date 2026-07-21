<?php

declare(strict_types=1);

namespace Nhwin\Settings\Tests\Fixtures;

use Nhwin\Settings\Contracts\ScopeResolver;

final class MutableScopeResolver implements ScopeResolver
{
    public function __construct(public string $scope = 'global') {}

    public function resolve(): string
    {
        return $this->scope;
    }
}
