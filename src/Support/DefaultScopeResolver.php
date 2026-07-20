<?php

declare(strict_types=1);

namespace Nhwin\Settings\Support;

use Nhwin\Settings\Contracts\ScopeResolver;

final class DefaultScopeResolver implements ScopeResolver
{
    public function resolve(): string
    {
        return 'global';
    }
}
