<?php

declare(strict_types=1);

namespace Nhwin\Settings\Repositories;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use JsonException;
use Nhwin\Settings\Contracts\SettingsRepository;
use RuntimeException;
use stdClass;

final class DatabaseSettingsRepository implements SettingsRepository
{
    public function __construct(private DatabaseManager $database) {}

    public function getGroup(string $scope, string $group): array
    {
        $settings = [];

        $this->query($scope, $group)->get()->each(function (stdClass $setting) use (&$settings): void {
            $settings[$setting->key] = json_decode(
                $setting->value,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        });

        return $settings;
    }

    public function setMany(string $scope, string $group, array $values): void
    {
        if ($values === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($values as $key => $value) {
            try {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException(
                    "Unable to serialize setting '{$group}.{$key}' to JSON.",
                    previous: $exception,
                );
            }

            $rows[] = [
                'scope' => $scope,
                'group' => $group,
                'key' => $key,
                'value' => $encoded,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->database->table($this->table())->upsert(
            $rows,
            ['scope', 'group', 'key'],
            ['value', 'updated_at'],
        );
    }

    public function lastUpdatedAt(string $scope, string $group): ?CarbonInterface
    {
        $timestamp = $this->query($scope, $group)->max('updated_at');

        return $timestamp ? Carbon::parse($timestamp, config('app.timezone', 'UTC')) : null;
    }

    public function forget(string $scope, string $group, string $key): void
    {
        $this->query($scope, $group)->where('key', $key)->delete();
    }

    public function forgetGroup(string $scope, string $group): void
    {
        $this->query($scope, $group)->delete();
    }

    private function query(string $scope, string $group): Builder
    {
        return $this->database->table($this->table())
            ->where('scope', $scope)
            ->where('group', $group);
    }

    private function table(): string
    {
        return (string) config('settings.table_name', 'settings');
    }
}
