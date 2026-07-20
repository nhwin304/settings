<?php

declare(strict_types=1);

namespace Nhwin\Settings\Services;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Nhwin\Settings\Contracts\ScopeResolver;
use Nhwin\Settings\Contracts\SettingsManagerContract;
use Nhwin\Settings\Contracts\SettingsRepository;
use Nhwin\Settings\Definitions\DefinitionRegistry;
use Nhwin\Settings\Events\SettingsGroupUpdated;
use Nhwin\Settings\Events\SettingUpdated;
use Nhwin\Settings\Exceptions\InvalidSettingType;
use Nhwin\Settings\Support\SettingKey;
use Nhwin\Settings\Support\SettingsCache;

class SettingsManager implements SettingsManagerContract
{
    private const ENCRYPTED_MARKER = '__nhwin_encrypted';

    private const REDACTED = '[encrypted]';

    protected string $scope;

    public function __construct(
        protected SettingsRepository $repository,
        protected SettingsCache $cache,
        protected DatabaseManager $database,
        protected Encrypter $encrypter,
        protected Dispatcher $events,
        protected DefinitionRegistry $definitions,
        ScopeResolver $scopeResolver,
    ) {
        $this->scope = $scopeResolver->resolve();
    }

    public function forScope(string $scope): static
    {
        $manager = clone $this;
        $manager->scope = $scope;

        return $manager;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parsed = SettingKey::parse($key);
        $group = $this->getGroup($parsed->group);
        $path = $parsed->root.($parsed->nestedPath ? '.'.$parsed->nestedPath : '');

        return data_get($group, $path, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $parsed = SettingKey::parse($key);

        if ($parsed->nestedPath === null) {
            $this->setMany($parsed->group, [$parsed->root => $value]);

            return;
        }

        $root = $this->rawGroup($parsed->group)[$parsed->root] ?? [];

        if (! is_array($root)) {
            $root = [];
        }

        data_set($root, $parsed->nestedPath, $value);
        $this->setMany($parsed->group, [$parsed->root => $root]);
    }

    public function setEncrypted(string $key, mixed $value): void
    {
        $this->setParsed(SettingKey::parse($key), $this->encrypt($value));
    }

    public function setMany(string $group, array $values): void
    {
        $definition = $this->definitions->get($group);

        if ($definition !== null) {
            foreach ($definition->encrypted() as $path) {
                $missing = new \stdClass;
                $value = data_get($values, $path, $missing);

                if ($value !== $missing && ! $this->isEncrypted($value)) {
                    data_set($values, $path, $this->encrypt($value));
                }
            }
        }

        $this->persistMany($group, $values);
    }

    public function getGroup(string $group): array
    {
        $values = $this->decrypt($this->rawGroup($group));
        $definition = $this->definitions->get($group);

        if ($definition === null) {
            return $values;
        }

        $values = array_replace_recursive($definition->defaults(), $values);

        foreach ($definition->casts() as $path => $cast) {
            $missing = new \stdClass;
            $value = data_get($values, $path, $missing);

            if ($value === $missing || $value === null) {
                continue;
            }

            data_set($values, $path, match ($cast) {
                'string' => (string) $value,
                'integer' => (int) $value,
                'float' => (float) $value,
                'boolean' => (bool) $value,
                'array' => (array) $value,
            });
        }

        return $values;
    }

    public function getGroupLastUpdatedAt(
        string $group,
        string $format = 'H:i:s d/m/Y',
        ?string $timezone = null,
    ): ?string {
        $updatedAt = $this->repository->lastUpdatedAt($this->scope, $group);

        if ($updatedAt === null) {
            return null;
        }

        return $updatedAt
            ->setTimezone($timezone ?? (string) config('app.timezone', 'UTC'))
            ->format($format);
    }

    public function string(string $key, ?string $default = null): string
    {
        return $this->typed($key, $default, 'string', is_string(...));
    }

    public function integer(string $key, ?int $default = null): int
    {
        return $this->typed($key, $default, 'integer', is_int(...));
    }

    public function float(string $key, ?float $default = null): float
    {
        return $this->typed($key, $default, 'float', is_float(...));
    }

    public function boolean(string $key, ?bool $default = null): bool
    {
        return $this->typed($key, $default, 'boolean', is_bool(...));
    }

    /**
     * @param  array<mixed>|null  $default
     * @return array<mixed>
     */
    public function array(string $key, ?array $default = null): array
    {
        return $this->typed($key, $default, 'array', is_array(...));
    }

    /**
     * @param  array<mixed>|null  $default
     * @return Collection<array-key, mixed>
     */
    public function collection(string $key, ?array $default = null): Collection
    {
        return new Collection($this->array($key, $default));
    }

    /** @return array<string, mixed> */
    protected function rawGroup(string $group): array
    {
        return $this->cache->remember(
            $this->scope,
            $group,
            fn (): array => $this->repository->getGroup($this->scope, $group),
        );
    }

    protected function setParsed(SettingKey $parsed, mixed $value): void
    {
        if ($parsed->nestedPath === null) {
            $this->persistMany($parsed->group, [$parsed->root => $value]);

            return;
        }

        $root = $this->rawGroup($parsed->group)[$parsed->root] ?? [];

        if (! is_array($root)) {
            $root = [];
        }

        data_set($root, $parsed->nestedPath, $value);
        $this->persistMany($parsed->group, [$parsed->root => $root]);
    }

    /** @param array<string, mixed> $values */
    protected function persistMany(string $group, array $values): void
    {
        if ($values === []) {
            return;
        }

        $scope = $this->scope;
        $oldGroup = $this->rawGroup($group);
        $changedKeys = array_values(array_filter(
            array_keys($values),
            fn (string $key): bool => ! array_key_exists($key, $oldGroup) || $oldGroup[$key] !== $values[$key],
        ));

        if ($changedKeys === []) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        foreach ($changedKeys as $key) {
            $oldValues[$key] = $this->safeEventValue($oldGroup[$key] ?? null);
            $newValues[$key] = $this->safeEventValue($values[$key]);
        }

        $this->database->transaction(function () use (
            $scope,
            $group,
            $values,
            $changedKeys,
            $oldValues,
            $newValues,
        ): void {
            $this->repository->setMany($scope, $group, $values);
            $this->database->afterCommit(function () use (
                $scope,
                $group,
                $changedKeys,
                $oldValues,
                $newValues,
            ): void {
                $this->cache->forget($scope, $group);

                foreach ($changedKeys as $key) {
                    $this->events->dispatch(new SettingUpdated(
                        $scope,
                        $group,
                        $key,
                        $oldValues[$key],
                        $newValues[$key],
                    ));
                }

                $this->events->dispatch(new SettingsGroupUpdated(
                    $scope,
                    $group,
                    $changedKeys,
                    $oldValues,
                    $newValues,
                ));
            });
        });
    }

    /** @param callable(mixed): bool $validator */
    private function typed(string $key, mixed $default, string $expected, callable $validator): mixed
    {
        $value = $this->get($key, $default);

        if (! $validator($value)) {
            throw InvalidSettingType::forKey($key, $expected, $value);
        }

        return $value;
    }

    /** @return array{__nhwin_encrypted: string} */
    private function encrypt(mixed $value): array
    {
        return [self::ENCRYPTED_MARKER => $this->encrypter->encrypt($value)];
    }

    private function decrypt(mixed $value): mixed
    {
        if ($this->isEncrypted($value)) {
            return $this->encrypter->decrypt($value[self::ENCRYPTED_MARKER]);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $nested) {
            $value[$key] = $this->decrypt($nested);
        }

        return $value;
    }

    private function isEncrypted(mixed $value): bool
    {
        return is_array($value)
            && count($value) === 1
            && isset($value[self::ENCRYPTED_MARKER])
            && is_string($value[self::ENCRYPTED_MARKER]);
    }

    private function safeEventValue(mixed $value): mixed
    {
        if ($this->containsEncryptedValue($value)) {
            return self::REDACTED;
        }

        return $this->decrypt($value);
    }

    private function containsEncryptedValue(mixed $value): bool
    {
        if ($this->isEncrypted($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $nested) {
            if ($this->containsEncryptedValue($nested)) {
                return true;
            }
        }

        return false;
    }
}
