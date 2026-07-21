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
use Nhwin\Settings\Events\SettingDeleted;
use Nhwin\Settings\Events\SettingsGroupDeleted;
use Nhwin\Settings\Events\SettingsGroupUpdated;
use Nhwin\Settings\Events\SettingUpdated;
use Nhwin\Settings\Exceptions\InvalidSettingType;
use Nhwin\Settings\Support\BooleanCaster;
use Nhwin\Settings\Support\SettingKey;
use Nhwin\Settings\Support\SettingsCache;

class SettingsManager implements SettingsManagerContract
{
    private const ENCRYPTED_MARKER = '__nhwin_encrypted';

    private const REDACTED = '[encrypted]';

    protected ?string $explicitScope = null;

    public function __construct(
        protected SettingsRepository $repository,
        protected SettingsCache $cache,
        protected DatabaseManager $database,
        protected Encrypter $encrypter,
        protected Dispatcher $events,
        protected DefinitionRegistry $definitions,
        protected ScopeResolver $scopeResolver,
    ) {}

    public function forScope(string $scope): static
    {
        $manager = clone $this;
        $manager->explicitScope = $scope;

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

    public function clearEncrypted(string $key): void
    {
        $this->setParsed(SettingKey::parse($key), null);
    }

    public function forget(string $key): void
    {
        $parsed = SettingKey::parse($key);
        $scope = $this->currentScope();
        $oldGroup = $this->rawGroup($parsed->group);

        if (! array_key_exists($parsed->root, $oldGroup)) {
            return;
        }

        if ($parsed->nestedPath === null) {
            $oldValue = $this->safeEventValue($oldGroup[$parsed->root]);
            $this->deleteRoot($scope, $parsed->group, $parsed->root, $parsed->root, $oldValue);

            return;
        }

        $root = $oldGroup[$parsed->root];

        if (! is_array($root)) {
            return;
        }

        $missing = new \stdClass;
        $oldValue = data_get($root, $parsed->nestedPath, $missing);

        if ($oldValue === $missing) {
            return;
        }

        data_forget($root, $parsed->nestedPath);
        $eventKey = $parsed->root.'.'.$parsed->nestedPath;
        $safeOldValue = $this->safeEventValue($oldValue);

        if ($root === []) {
            $this->deleteRoot($scope, $parsed->group, $parsed->root, $eventKey, $safeOldValue);

            return;
        }

        $this->database->transaction(function () use (
            $scope,
            $parsed,
            $root,
            $eventKey,
            $safeOldValue,
        ): void {
            $this->repository->setMany($scope, $parsed->group, [$parsed->root => $root]);
            $this->database->afterCommit(function () use ($scope, $parsed, $eventKey, $safeOldValue): void {
                $this->cache->forget($scope, $parsed->group);
                $this->events->dispatch(new SettingDeleted(
                    $scope,
                    $parsed->group,
                    $eventKey,
                    $safeOldValue,
                ));
            });
        });
    }

    public function forgetGroup(string $group): void
    {
        $scope = $this->currentScope();
        $oldGroup = $this->rawGroup($group);

        if ($oldGroup === []) {
            return;
        }

        $oldValues = array_map($this->safeEventValue(...), $oldGroup);
        $deletedKeys = array_keys($oldGroup);

        $this->database->transaction(function () use ($scope, $group, $oldValues, $deletedKeys): void {
            $this->repository->forgetGroup($scope, $group);
            $this->database->afterCommit(function () use ($scope, $group, $oldValues, $deletedKeys): void {
                $this->cache->forget($scope, $group);
                $this->events->dispatch(new SettingsGroupDeleted(
                    $scope,
                    $group,
                    $deletedKeys,
                    $oldValues,
                ));
            });
        });
    }

    public function setMany(string $group, array $values): void
    {
        $definition = $this->definitions->get($group);

        if ($definition !== null) {
            $rawGroup = $this->rawGroup($group);

            foreach ($definition->encrypted() as $path) {
                $root = explode('.', $path, 2)[0];

                if (! array_key_exists($root, $values)) {
                    continue;
                }

                $missing = new \stdClass;
                $value = data_get($values, $path, $missing);
                $stored = data_get($rawGroup, $path, $missing);

                if ($value === $missing || $this->isBlankSecret($value)) {
                    if ($stored !== $missing) {
                        data_set($values, $path, $stored);
                    }

                    continue;
                }

                if ($this->isEncrypted($value)) {
                    continue;
                }

                if ($stored !== $missing && $this->isEncrypted($stored) && $this->decrypt($stored) === $value) {
                    data_set($values, $path, $stored);

                    continue;
                }

                data_set($values, $path, $this->encrypt($value));
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
                'boolean' => BooleanCaster::cast("{$group}.{$path}", $value),
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
        $updatedAt = $this->repository->lastUpdatedAt($this->currentScope(), $group);

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
        $value = $this->get($key, $default);

        if (! is_int($value) && ! is_float($value)) {
            throw InvalidSettingType::forKey($key, 'float', $value);
        }

        return (float) $value;
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
        $scope = $this->currentScope();

        return $this->cache->remember(
            $scope,
            $group,
            fn (): array => $this->repository->getGroup($scope, $group),
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

        $scope = $this->currentScope();
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
        $changedValues = array_intersect_key($values, array_flip($changedKeys));

        foreach ($changedKeys as $key) {
            $oldValues[$key] = $this->safeEventValue($oldGroup[$key] ?? null);
            $newValues[$key] = $this->safeEventValue($values[$key]);
        }

        $this->database->transaction(function () use (
            $scope,
            $group,
            $changedValues,
            $changedKeys,
            $oldValues,
            $newValues,
        ): void {
            $this->repository->setMany($scope, $group, $changedValues);
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

    protected function currentScope(): string
    {
        return $this->explicitScope ?? $this->scopeResolver->resolve();
    }

    private function deleteRoot(
        string $scope,
        string $group,
        string $root,
        string $eventKey,
        mixed $safeOldValue,
    ): void {
        $this->database->transaction(function () use ($scope, $group, $root, $eventKey, $safeOldValue): void {
            $this->repository->forget($scope, $group, $root);
            $this->database->afterCommit(function () use ($scope, $group, $eventKey, $safeOldValue): void {
                $this->cache->forget($scope, $group);
                $this->events->dispatch(new SettingDeleted(
                    $scope,
                    $group,
                    $eventKey,
                    $safeOldValue,
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

    private function isBlankSecret(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
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
