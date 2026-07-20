---
name: nhwin-settings-modernization
description: >
  Audit and modernize the nhwin304/settings Laravel Filament plugin. Fix namespace and
  runtime correctness issues first, then refactor the settings engine for production use,
  preserve backward compatibility, add PHP 8.3+ support, and support both Filament 4 and
  Filament 5 with an explicit compatibility test matrix.
---

# nhwin/settings Modernization Skill

## Purpose

Use this skill when working on the `nhwin304/settings` repository or a local checkout of the
`nhwin/settings` package.

The goal is to evolve the package into a production-grade, framework-agnostic settings engine
with optional Filament integration while preserving the package's strongest existing design:

- Database storage based on `group + key + JSON value`.
- Unique constraint on `(group, key)`.
- Dot-notation access such as `settings('general.site_name')`.
- Multiple independent settings groups.
- Generated Filament settings pages.
- Multi-panel-aware page generation.
- Cache-backed reads.
- Lightweight package dependencies.

The implementation should borrow the best architectural ideas from:

- `outer-web/filament-settings`: clean separation between settings engine and Filament UI,
  Filament-native page lifecycle, transactions, mutation hooks, unsaved-change protection.
- `tomatophp/filament-settings-hub`: optional Settings Hub and page registry.
- `joaopaulolndev/filament-general-settings`: convenient ready-made presets, but presets must
  stay optional and must not hard-code the core database schema.

Do not copy code blindly from third-party packages. Reimplement the concepts in the style and
namespace of this package.

---

# Target Compatibility

The completed package must support:

```json
{
    "php": "^8.3",
    "laravel/framework": "^11.28|^12.0",
    "filament/filament": "^4.0|^5.0"
}
```

Filament 5 support must account for its Livewire 4 dependency in the test environment.

Do not unnecessarily add `livewire/livewire` as a direct production dependency if Filament
already owns that dependency. Add it explicitly only to test/dev constraints when Composer
resolution requires it.

Do not remove Filament 4 compatibility while adding Filament 5.

When an API differs between Filament 4 and Filament 5:

1. Prefer an API that exists in both versions.
2. Otherwise isolate compatibility behavior behind a small adapter/compatibility class.
3. Do not scatter `version_compare()` or package-version checks throughout page classes.
4. Add tests covering both supported Filament major versions.

---

# Non-Negotiable Backward Compatibility

Existing consumers must continue to work unless a documented breaking change is absolutely
required.

The following APIs must remain supported:

```php
settings('general.site_name');

settings('general.site_name', 'Default');

Setting::get('general.site_name');

Setting::set('general.site_name', 'My Site');

Setting::getGroup('general');

Setting::getGroupLastUpdatedAt('general');
```

The Blade directive must remain available:

```blade
@settings('general.site_name', 'Default')
```

New object-oriented APIs may be added, but the existing helper and facade must delegate to the
new implementation.

---

# Execution Rules

Work in phases and complete each phase before starting the next.

After every phase:

1. Run Pint or the package formatter.
2. Run static analysis.
3. Run the relevant test subset.
4. Fix all regressions introduced by the phase.
5. Do not continue with a knowingly broken test suite.

Do not commit, push, tag, publish a release, or open a pull request unless explicitly requested.

Prefer small cohesive classes over one large static utility class.

---

# Phase 0 — Repository Audit and Correctness Fixes

Before architectural refactoring, inspect the entire repository for stale namespaces and broken
imports.

Search for at least:

```text
Huythang304
Nhwin304
Nhwin\Supports
Nhwin\Settings\AbstractPageSettings
```

Standardize the production namespace to:

```text
Nhwin\Settings
```

Standardize the test namespace to:

```text
Nhwin\Settings\Tests
```

## Required fixes

### Facade

Ensure the facade resolves the real settings service/manager.

Incorrect historical pattern:

```php
use Nhwin\Supports\Setting as SettingService;
```

Correct namespace must be under:

```php
Nhwin\Settings\...
```

### Abstract settings page

Ensure `AbstractPageSettings` imports the intended facade or manager explicitly.

Never rely on PHP resolving:

```text
Nhwin\Settings\Abstracts\Setting
```

when the real class lives elsewhere.

### Generator stub

The generated page must import:

```php
use Nhwin\Settings\Abstracts\AbstractPageSettings;
```

or the final equivalent namespace chosen during refactoring.

The generated file must boot successfully without manual namespace corrections.

### Pest bootstrap

Update stale test imports to:

```php
use Nhwin\Settings\Tests\TestCase;
```

### Command return types

Do not return `Command::FAILURE` from a method declared to return `string`.

Validation failures must be handled in `handle()` or by throwing a meaningful exception from an
internal method with a compatible return contract.

## Required tests

Add regression tests proving:

- The facade resolves successfully.
- `settings()` resolves successfully.
- The Blade directive renders successfully.
- `make:settings` generates a class with valid namespaces.
- A generated settings page can be autoloaded.
- Non-interactive generator failure returns `Command::FAILURE` without a `TypeError`.

---

# Phase 1 — Introduce a Proper Settings Engine

Refactor the existing static `Supports\Setting` responsibilities into explicit layers.

Recommended structure:

```text
src/
├── Contracts/
│   ├── SettingsManagerContract.php
│   └── SettingsRepository.php
├── Services/
│   └── SettingsManager.php
├── Repositories/
│   └── DatabaseSettingsRepository.php
├── Support/
│   ├── SettingKey.php
│   └── SettingsCache.php
├── Models/
│   └── Setting.php
├── Facades/
│   └── Setting.php
└── Helpers/
    └── helpers.php
```

The exact names may be adjusted when justified, but responsibilities must remain separated.

## SettingsManager responsibilities

The manager coordinates:

- Parsing dot-notation keys.
- Reading groups.
- Reading nested values.
- Writing a single value.
- Writing multiple values.
- Cache interaction.
- Type-safe getters.
- Optional encryption.
- Scope resolution.
- Event dispatching.

It must not contain raw query-builder details.

## SettingsRepository responsibilities

The repository owns database persistence:

```php
public function getGroup(string $scope, string $group): array;

public function setMany(string $scope, string $group, array $values): void;

public function lastUpdatedAt(string $scope, string $group): ?CarbonInterface;
```

Use the query builder or Eloquent consistently. Do not mix raw queries in helpers and a separate
repository in other paths.

Bind the repository contract in the package service provider so advanced consumers can replace
the storage implementation.

---

# Phase 2 — Correct Dot-Notation Semantics

Define key parsing explicitly.

Examples:

```text
general.site_name
```

means:

```text
group = general
root key = site_name
nested path = null
```

Example:

```text
general.social.facebook
```

means:

```text
group = general
root key = social
nested path = facebook
```

Example:

```text
general.social.links.facebook
```

means:

```text
group = general
root key = social
nested path = links.facebook
```

## Read behavior

```php
settings('general.social.facebook');
```

must load the `general` group and return the nested value.

## Write behavior

```php
Setting::set('general.social.facebook', 'https://facebook.com/example');
```

must update only the nested `facebook` value.

It must not overwrite the complete `social` object with a string.

Use `data_get()` and `data_set()` or equivalent well-tested behavior.

Add tests for:

- Scalar root values.
- Array root values.
- Two-level nesting.
- Deep nesting.
- Missing keys and defaults.
- Updating one nested key while preserving sibling keys.
- Null values.

---

# Phase 3 — Group-Level Cache

Replace per-setting cache as the primary caching strategy with group-level cache.

Recommended cache key:

```text
{prefix}:{scope}:{group}
```

Example:

```text
settings:global:general
```

A group cache contains the complete group:

```php
[
    'site_name' => 'TruyenNo1',
    'maintenance' => false,
    'social' => [
        'facebook' => '...',
    ],
]
```

Then:

```php
settings('general.site_name');
settings('general.social.facebook');
settings('general.maintenance');
```

must reuse the same cached group.

Preserve configuration for:

- Cache prefix.
- Cache TTL.
- Forever caching when TTL is `null` or `0`.

## Cache invalidation order

Never invalidate cache before the database transaction commits.

Required order:

```text
BEGIN TRANSACTION
write database
COMMIT
forget or refresh group cache
```

Prefer:

```php
DB::transaction(...);

DB::afterCommit(...);
```

when appropriate.

Prevent the race condition where another request repopulates stale cache between cache eviction
and database update.

Add tests for:

- Cache miss.
- Cache hit.
- Forever cache.
- TTL cache.
- Cache invalidation after save.
- Cache remains consistent after failed transaction.

---

# Phase 4 — Bulk Writes with setMany()

Add a bulk API:

```php
Setting::setMany('general', [
    'site_name' => 'TruyenNo1',
    'maintenance' => false,
    'social' => [
        'facebook' => '...',
    ],
]);
```

Also expose it through the manager:

```php
settings()->setMany(...);
```

If keeping `settings()` as a read helper only is preferred for backward compatibility, provide a
separate `setting()` manager resolver or facade API, but do not break existing calls.

The repository should use one bulk `upsert()` operation per group whenever possible.

A Filament settings page save must use:

```text
1 form state read
1 transaction
1 bulk persistence operation
1 group cache invalidation
```

Do not call `Setting::set()` once per top-level field.

---

# Phase 5 — Filament-Native Abstract Settings Page

Upgrade the base settings page using Filament-native behavior inspired by the strongest parts of
Outerweb's implementation.

Add, where compatible with Filament 4 and 5:

```php
use CanUseDatabaseTransactions;
use HasUnsavedDataChangesAlert;
```

The page lifecycle should support:

```text
mount
├── beforeFill
├── read group
├── merge defaults
├── mutateFormDataBeforeFill
├── fill form
└── afterFill

save
├── begin transaction
├── beforeValidate
├── get validated form state
├── afterValidate
├── mutateFormDataBeforeSave
├── beforeSave
├── bulk setMany
├── save field relationships when applicable
├── afterSave
├── commit
├── remember unsaved-data state
└── success notification
```

Provide overridable hooks:

```php
protected function mutateFormDataBeforeFill(array $data): array
{
    return $data;
}

protected function mutateFormDataBeforeSave(array $data): array
{
    return $data;
}
```

Preserve:

```php
protected function settingName(): string;
```

or replace it with a clearer equivalent only if generated pages remain backward compatible.

Keep `mod+s` / Ctrl+S save shortcut.

Do not force every page to use a custom Blade view when Filament's schema page rendering can
handle it cleanly. Preserve old generated pages when possible.

---

# Phase 6 — Settings Hub Registry

Add an optional Settings Hub modeled conceptually after TomatoPHP, but implement it natively.

The hub must not be required for basic settings usage.

Recommended API:

```php
SettingsPlugin::make()
    ->pages([
        GeneralSettings::class,
        SeoSettings::class,
        MailSettings::class,
    ])
    ->hub();
```

Support page metadata:

```php
SettingsPageDefinition::make(GeneralSettings::class)
    ->label('General settings')
    ->description('Application identity and defaults')
    ->icon('heroicon-o-cog-6-tooth')
    ->group('System')
    ->sort(1);
```

The registry should support:

- Page class.
- Label.
- Description.
- Icon.
- Group/category.
- Sort order.
- Optional badge.
- Optional URL/route for external settings destinations.
- Optional access callback.

Do not use global mutable static configuration for per-panel plugin options.

Each Filament panel must be able to configure its own plugin instance independently.

Example:

```text
Admin Panel
└── full settings hub

Author Panel
└── limited settings hub
```

---

# Phase 7 — Type-Safe Getters and Optional Definitions

Keep dynamic settings as the default.

Add type-safe getters:

```php
Setting::string('general.site_name');
Setting::integer('limits.upload_mb');
Setting::float('payment.fee');
Setting::boolean('general.maintenance');
Setting::array('general.social');
Setting::collection('general.social');
```

Throw a clear exception when the stored type is invalid.

Optionally introduce setting definitions without requiring Spatie Laravel Settings:

```php
final class GeneralSettingsDefinition extends SettingsDefinition
{
    public static function group(): string
    {
        return 'general';
    }

    public function defaults(): array
    {
        return [
            'site_name' => '',
            'maintenance' => false,
        ];
    }

    public function casts(): array
    {
        return [
            'site_name' => 'string',
            'maintenance' => 'boolean',
        ];
    }

    public function encrypted(): array
    {
        return [];
    }
}
```

Definitions must be optional. Existing dynamic groups must continue to work without a definition
class.

---

# Phase 8 — Encrypted Settings

Add first-class encrypted values for secrets.

Support at least one explicit API:

```php
Setting::setEncrypted('mail.smtp.password', $password);
```

Prefer also supporting definition-based encryption:

```php
public function encrypted(): array
{
    return [
        'smtp.password',
        'mailgun.secret',
        'postmark.token',
        'ses.secret',
    ];
}
```

Use Laravel's encryption facilities.

Encryption must happen before persistence and decryption after retrieval.

Never expose decrypted secrets through debug output, exceptions, logs, or generated audit
payloads.

Add tests for:

- Encrypted database payload differs from plaintext.
- Read returns plaintext to authorized application code.
- Invalid/corrupted encrypted payload fails predictably.
- Updating unrelated values does not corrupt encrypted siblings.

---

# Phase 9 — Scope and Multi-Tenancy Foundation

Add optional scope support without coupling the package directly to Filament tenancy.

Recommended schema:

```text
settings
├── id
├── scope
├── group
├── key
├── value
├── created_at
└── updated_at
```

Unique key:

```text
(scope, group, key)
```

Default scope:

```text
global
```

API example:

```php
Setting::forScope('tenant:15')
    ->get('general.site_name');
```

Allow a configurable scope resolver:

```php
'scope_resolver' => DefaultScopeResolver::class,
```

Do not hard-code `tenant_id`.

Provide a safe migration path for existing installations. Existing rows should become:

```text
scope = global
```

Cache keys must include scope.

This phase may be postponed to a later release if introducing the schema change would make the
current release too risky, but design the manager/repository contracts so scope can be added
without another major rewrite.

---

# Phase 10 — Events

Dispatch domain events after successful committed changes:

```text
SettingUpdated
SettingsGroupUpdated
```

Include useful metadata:

- Scope.
- Group.
- Changed keys.
- Old values when safe.
- New values when safe.

Do not include plaintext secret values in events.

Consumers should be able to react to settings changes, for example:

```text
Mail settings changed
→ reload runtime mail configuration

Theme settings changed
→ clear theme cache

SEO settings changed
→ regenerate sitemap
```

Keep these reactions outside the core package.

---

# Phase 11 — Permissions

Provide package-native authorization hooks without making Filament Shield a hard dependency.

Plugin-level example:

```php
SettingsPlugin::make()
    ->canAccess(fn (): bool => auth()->user()?->can('settings.view') ?? false);
```

Page-level access should also be supported.

Optionally provide a Shield integration adapter when Shield is installed.

The core package must remain usable without Shield.

---

# Phase 12 — Optional Presets

Borrow the convenience of general-settings packages without hard-coding their schema into the
core.

Add optional presets/templates such as:

```text
general
seo
mail
social
analytics
```

Example generator commands:

```bash
php artisan make:settings General --preset=general
php artisan make:settings SEO --preset=seo
php artisan make:settings Mail --preset=mail
```

Potential options:

```text
--panel=admin
--group=general
--hub
--preset=general
--typed
--force
```

A preset may generate:

- A Filament page.
- A definition class.
- Default fields.
- Optional hub registration metadata.

Presets must not add fixed columns to the `settings` table.

---

# Phase 13 — Generator Improvements

Preserve multi-panel support.

The generator must:

- Detect existing Filament panels.
- Generate valid namespaces for both default and named panels.
- Use platform-independent paths.
- Refuse to overwrite files unless `--force` is provided.
- Work with `--no-interaction`.
- Generate Filament 4/5-compatible code.
- Generate a page that passes autoload and syntax checks.

Avoid `ucfirst($panel)` as the only namespace normalization strategy.

Use a proper StudlyCase transformation:

```php
Str::studly($panel)
```

Add tests for panel IDs containing:

```text
admin
super-admin
author_panel
```

---

# Phase 14 — Composer and Compatibility Work

Update Composer constraints for the requested baseline:

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.28|^12.0",
        "filament/filament": "^4.0|^5.0"
    }
}
```

Before finalizing, run Composer resolution for every supported combination.

Do not claim a compatibility combination that has not passed CI.

If Filament 4 and 5 cannot be tested under one shared `composer.json` test matrix directly,
use a matrix script that modifies constraints with `composer require --no-update` before
`composer update`.

Keep production dependencies minimal.

---

# Phase 15 — CI Matrix

Replace the single-version CI job with a real compatibility matrix.

Minimum target matrix:

```text
PHP 8.3 + Laravel 11.28+ + Filament 4
PHP 8.3 + Laravel 11.28+ + Filament 5
PHP 8.3 + Laravel 12     + Filament 4
PHP 8.3 + Laravel 12     + Filament 5
PHP 8.4 + Laravel 12     + Filament 4
PHP 8.4 + Laravel 12     + Filament 5
```

Only retain combinations Composer can actually resolve.

For Filament 5 jobs, ensure Livewire 4 is resolved.

CI must run:

```text
composer validate --strict
Pint/style check
PHPStan
Pest
```

Use current stable GitHub Actions versions.

Do not run code coverage on every matrix combination unless needed. Use one dedicated coverage
job to reduce CI time.

---

# Phase 16 — Test Suite Requirements

Create or expand the following tests.

## Unit

```text
SettingKeyTest
SettingsManagerTest
SettingsRepositoryTest
SettingsCacheTest
NestedSettingsTest
TypeSafeGetterTest
EncryptionTest
ScopeTest
```

## Feature

```text
SettingsDatabaseTest
SettingsPageTest
SettingsSaveLifecycleTest
SettingsHubTest
MakeSettingsCommandTest
MultiPanelGeneratorTest
BladeDirectiveTest
FacadeTest
CompatibilitySmokeTest
```

Critical scenarios:

- First read populates cache.
- Repeated reads avoid database queries where expected.
- Bulk save persists all fields.
- Failed transaction does not expose partially persisted state.
- Nested update preserves sibling values.
- Defaults merge correctly.
- Existing helper API remains compatible.
- Generated Filament page works on both Filament 4 and 5.
- Per-panel hub configuration does not leak to another panel.
- Encrypted values are not stored as plaintext.

---

# Phase 17 — Documentation

Rewrite README sections so they match the final code exactly.

Document:

1. Installation.
2. Compatibility table.
3. Basic helper usage.
4. Facade usage.
5. Nested settings.
6. Group reads.
7. Bulk writes.
8. Cache behavior.
9. Type-safe getters.
10. Encryption.
11. Generated Filament pages.
12. Settings Hub.
13. Permissions.
14. Multi-panel usage.
15. Scope/tenant usage if implemented.
16. Upgrade guide from the previous package version.

The compatibility table must clearly state verified versions rather than theoretical support.

Example:

```text
Package    PHP    Laravel    Filament
next       8.3+   11.28/12   4.x/5.x
```

---

# Definition of Done

The modernization is complete only when all of the following are true:

- No stale `Huythang304`, `Nhwin304`, or incorrect `Nhwin\Supports` namespace remains.
- Composer autoload succeeds.
- `settings()` helper works.
- Facade works.
- Blade directive works.
- Nested get/set semantics are symmetric.
- Page saves use bulk persistence.
- Cache is group-based and invalidated after successful commit.
- Filament settings pages have native lifecycle protections.
- Settings Hub is optional.
- Typed getters work.
- Encryption is available for secrets.
- PHP 8.3 compatibility is tested.
- Filament 4 compatibility is tested.
- Filament 5 compatibility is tested.
- CI passes for every advertised compatibility combination.
- README matches the implemented APIs.
- No production dependency is added without a clear architectural reason.

---

# Recommended Implementation Order

Execute in this order:

```text
P0  Correct namespaces and broken runtime imports
P0  Repair test bootstrap and generator output
P0  Establish passing baseline tests

P1  Introduce manager/repository contracts
P1  Fix nested key semantics
P1  Add group-level cache
P1  Add setMany() and transaction-safe invalidation

P2  Upgrade AbstractPageSettings lifecycle
P2  Add Filament 4/5 compatibility layer
P2  Add SettingsPlugin and optional Settings Hub

P3  Add typed getters and optional definitions
P3  Add encryption
P3  Add permissions and events

P4  Add scope foundation / multi-tenancy
P4  Add optional presets
P4  Expand generator

P5  Complete CI compatibility matrix
P5  Update documentation and upgrade guide
```

Do not start P3/P4 feature work while P0/P1 tests are failing.

---

# Final Report Format

When the work is complete, report:

```text
1. Fixed correctness issues
2. Architecture changes
3. Backward compatibility retained
4. New APIs
5. Filament 4 compatibility status
6. Filament 5 compatibility status
7. PHP 8.3 compatibility status
8. Test matrix results
9. Files added/changed
10. Remaining risks or intentionally deferred work
```

Be explicit about anything that was not completed or could not be verified.
