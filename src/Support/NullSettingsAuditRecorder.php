<?php

declare(strict_types=1);

namespace Nhwin\Settings\Support;

use Nhwin\Settings\Contracts\SettingsAuditRecorder;

final class NullSettingsAuditRecorder implements SettingsAuditRecorder
{
    public function record(SettingsAuditEntry $entry): void {}
}
