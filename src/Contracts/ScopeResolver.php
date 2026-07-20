<?php

declare(strict_types=1);

namespace Nhwin\Settings\Contracts;

interface ScopeResolver
{
    public function resolve(): string;
}
