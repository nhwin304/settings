<?php

declare(strict_types=1);

namespace Nhwin\Settings\Contracts;

use Nhwin\Settings\Support\SettingsAuditEntry;

interface SettingsAuditRecorder
{
    public function record(SettingsAuditEntry $entry): void;
}
