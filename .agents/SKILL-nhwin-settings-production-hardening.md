---
name: nhwin-settings-production-hardening
description: >
  Harden and upgrade nhwin304/settings after the latest review. Fix Livewire secret-state
  exposure, authorization boundaries, Laravel lower bounds, prefer-lowest CI, transactional
  nested writes, afterCommit lifecycle, casting consistency, audit granularity, and recursive
  secret redaction without rewriting the existing architecture.
---

# nhwin/settings Production Hardening Skill

## Goal

Apply the latest review findings to the current `nhwin304/settings` repository.

Do not rewrite the current architecture. Preserve:

- `SettingsManagerContract`
- `SettingsManager`
- `SettingsRepository`
- `DatabaseSettingsRepository`
- group-level cache
- scope/multi-tenancy support
- `setMany()`
- nested settings
- encryption
- typed getters
- optional definitions
- events and audit contract
- `AbstractPageSettings`
- `SettingsPageAdapter`
- `SettingsPlugin`
- `SettingsRegistry`
- `SettingsPageDefinition`
- Settings Hub
- generator presets
- Filament 4/5 support

## Public API Compatibility

Keep working:

```php
settings('general.site_name');
settings('general.site_name', 'Default');
settings()->setMany('general', []);

Setting::get('general.site_name');
Setting::set('general.site_name', 'value');
Setting::setMany('general', []);
Setting::setEncrypted('mail.smtp.password', 'secret');
Setting::clearEncrypted('mail.smtp.password');
Setting::forget('general.legacy_key');
Setting::forgetGroup('general');
Setting::getGroup('general');
Setting::getGroupLastUpdatedAt('general');
Setting::forScope('tenant:1')->get('general.site_name');
```

Keep Blade:

```blade
@settings('general.site_name', 'Default')
```

Target compatibility:

```text
PHP 8.3+
Laravel 11.28+, 12.x, 13.x when verified
Filament 4.x and 5.x
```

Do not commit, push, tag, publish, or open a PR unless explicitly requested.

---

# Execution Order

```text
P0/P1  Remove plaintext secret from Livewire/public page state after save
P1     Harden SettingsPageDefinition authorization contract
P1     Correct Laravel lower bound
P1     Add prefer-lowest compatibility CI
P2     Add afterCommit page lifecycle
P2     Improve nested concurrent writes
P2     Standardize Definition casting
P2     Align PHP compatibility wording
P3     Add configurable audit granularity
P3     Add recursive secret redaction
P3     Strengthen group/scope/key validation
```

Before changes run:

```bash
composer validate --strict
composer format:test
composer analyse
composer test
```

After every phase run:

```bash
composer format:test
composer analyse
composer test
```

Do not continue with known regressions.

---

# Phase 1 — Security: Clear Plaintext Secrets from Livewire State

## Problem

On mount, encrypted fields are blanked correctly.

After save, however, the page may do conceptually:

```php
$data = $this->adapter()->state($form);

Setting::setMany($this->settingName(), $data);

$this->commitDatabaseTransaction();

$this->data = $data;
```

`SettingsManager` encrypts its own values, but `$data` can still contain the plaintext secret.
Because `$data` is public Livewire state, the secret can remain in component/browser state after
save.

## Required behavior

After saving:

```text
DB                    -> ciphertext
Setting::get()        -> plaintext for application code
Livewire public state -> blank secret
Filament form state   -> blank secret
events                -> redacted
audit                  -> redacted
```

## Required implementation

Add one reusable sanitizer to `AbstractPageSettings`:

```php
/**
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
protected function sanitizeEncryptedFormData(array $data): array
{
    $definition = app(DefinitionRegistry::class)
        ->get($this->settingName());

    if ($definition === null) {
        return $data;
    }

    foreach ($definition->encrypted() as $path) {
        if (data_get($data, $path) !== null) {
            data_set($data, $path, '');
        }
    }

    return $data;
}
```

Use it:

1. During mount before filling the form.
2. After successful save.
3. Before assigning data back to public state.

Recommended sequence:

```php
$this->commitDatabaseTransaction();

$this->data = $this->sanitizeEncryptedFormData($data);

$this->adapter()->fill(
    $form,
    $this->data,
);

$this->rememberData();
```

Do not blank non-encrypted settings.

## Required tests

- New secret stores ciphertext.
- `Setting::get()` returns plaintext.
- `$page->data` contains no plaintext after save.
- Form state contains no plaintext after save.
- Existing encrypted fields mount as blank.
- Blank secret preserves existing ciphertext.
- Same plaintext preserves ciphertext.
- New plaintext replaces ciphertext.
- Explicit clear still works.
- Events and audit stay redacted.

---

# Phase 2 — Authorization Contract Hardening

## Problem

`SettingsPlugin::pages()` can accept arbitrary `Filament\Pages\Page` classes.

Definition-level route authorization is enforced by `AbstractPageSettings::canAccess()`.
Therefore generic pages that do not extend `AbstractPageSettings` may not inherit direct-route
protection from `SettingsPageDefinition::canAccess()`.

## Preferred solution

For automatically protected settings pages, require:

```php
AbstractPageSettings
```

Update type documentation and runtime validation.

Internal settings pages:

```php
SettingsPlugin::make()
    ->pages([
        GeneralSettings::class,
    ]);
```

must use classes extending `AbstractPageSettings`.

External links continue through:

```php
SettingsPageDefinition::external('/profile/settings');
```

If a generic Filament Page is passed as an internal settings page, throw a clear exception or route
it through a separately named API with explicitly documented authorization semantics.

## Alternative

Support arbitrary Filament Pages only if the README clearly says:

```text
SettingsPageDefinition::canAccess()
automatically protects direct routes only for AbstractPageSettings pages.
```

Do not claim universal direct-route protection if it is not technically enforced.

## Required authorization logic

For `AbstractPageSettings`:

```text
parent/native page access
AND
canAccessSettingsPage() hook
AND
plugin access callback
AND
definition access callback
```

Avoid recursion.

## Tests

- Plugin callback denial.
- Definition callback denial.
- Page hook denial.
- Direct route protection.
- Hub visibility.
- Multi-panel isolation.
- Generic Page registration behavior.
- External link definitions.
- Duplicate definition replacement.

---

# Phase 3 — Correct Laravel Lower Bound

Change inaccurate constraints such as:

```json
"laravel/framework": "^11.0|^12.0|^13.0"
```

to:

```json
"laravel/framework": "^11.28|^12.0|^13.0"
```

only keep Laravel 13 if verified.

README should state:

```text
Laravel 11.28+, 12.x, 13.x
```

not just:

```text
Laravel 11.x
```

CI Laravel 11 matrix should use:

```yaml
laravel: '^11.28'
```

Do not treat `^11.0` resolving to a newer version as proof that Laravel 11.0 is supported.

---

# Phase 4 — Add Real prefer-lowest CI

Normal `composer update` usually installs recent compatible versions and does not prove lower
bounds.

Keep current compatibility matrix and add dedicated lower-bound jobs.

At minimum test:

```text
PHP 8.3
Laravel 11.28+
Filament 4 lowest compatible
Livewire 3 compatible
Testbench 9
```

Also test Filament 5 lowest-compatible dependencies when resolvable.

Use:

```bash
composer update   --prefer-lowest   --prefer-stable   --with-all-dependencies   --no-interaction   --no-progress
```

Each lowest job must pass:

```text
composer validate --strict
Pint
PHPStan
Pest
```

Only advertise lower bounds that actually pass.

---

# Phase 5 — Add afterCommit() Page Lifecycle

## Problem

`afterSave()` runs before the outer page transaction commits.

SettingsManager invalidates cache in `DB::afterCommit()`.

Therefore:

```php
protected function afterSave(): void
{
    Setting::get('general.site_name');
}
```

may still observe old cached data.

## Required lifecycle

Preserve existing hooks and add:

```php
protected function afterCommit(): void
{
}
```

Recommended order:

```text
begin transaction
beforeValidate
get state
afterValidate
mutate data
beforeSave
persist
save relationships
afterSave
commit
afterCommit
sanitize encrypted form state
rememberData
notification
```

Document:

```text
afterSave   -> inside transaction
afterCommit -> committed state available
```

## Tests

- `afterSave()` occurs before commit.
- `afterCommit()` occurs after successful commit.
- `afterCommit()` does not run on rollback.
- Reading settings in `afterCommit()` returns new value.
- Existing lifecycle order stays compatible.

---

# Phase 6 — Improve Nested Concurrent Write Safety

## Problem

Nested values are stored inside one JSON root.

Two concurrent operations can read the same old root and overwrite each other's sibling updates.

Example:

```text
A: social.facebook = A
B: social.github   = B
```

Possible result:

```text
facebook change lost
github = B
```

## Preferred design

Move nested read-modify-write into one repository/database transaction using latest DB state.

Possible repository API:

```php
public function mutate(
    string $scope,
    string $group,
    string $key,
    Closure $callback,
): mixed;
```

Database implementation:

```text
BEGIN
SELECT row FOR UPDATE
decode JSON
callback mutates latest value
encode JSON
UPDATE
COMMIT
invalidate group cache after commit
```

Do not use cached root data as the authoritative source for nested mutations.

Avoid database-specific JSON path syntax unless portability is intentionally handled.

## Tests

Add at least regression coverage proving sequential mutations based on the latest committed root
preserve sibling changes.

Document DB-specific concurrency limitations.

---

# Phase 7 — Standardize Definition Casting

## Problem

Boolean casting is validated, while other casts may use permissive PHP coercion.

Examples:

```php
(int) 'abc'   // 0
(array) 'abc' // ['abc']
```

This can hide invalid settings.

## Preferred semantics

Introduce explicit casters or equivalent validated methods.

### string

Accept strings.

### integer

Accept integers.
Optionally accept integer strings only if documented.

### float

Accept int or float.
Return float.

### boolean

Keep strict `BooleanCaster`.

### array

Accept arrays only.

Invalid values should throw `InvalidSettingType` or a dedicated cast exception.

Never include secret plaintext in exception messages.

## Document distinction

```text
Definition casts
-> validated normalization of stored group values

Typed getters
-> validation when application code requests a specific type
```

## Tests

Cover valid and invalid inputs for every supported cast.

---

# Phase 8 — Align PHP Support Wording

If Composer uses:

```json
"php": "^8.3"
```

README should normally say:

```text
PHP 8.3+
```

rather than only:

```text
PHP 8.3 hoặc 8.4
```

CI should cover:

```text
lowest supported PHP: 8.3
current stable versions supported by the project
```

Do not claim untested future PHP versions as verified.

Differentiate clearly between:

```text
Composer-allowed
```

and:

```text
CI-verified
```

---

# Phase 9 — Configurable Audit Granularity

One bulk change can emit:

```text
SettingUpdated A
SettingUpdated B
SettingsGroupUpdated
```

A recorder listening to all events can create duplicate-looking audit records.

Add optional config:

```php
'audit' => [
    'granularity' => 'setting',
],
```

Allowed values:

```text
setting
group
both
```

Suggested behavior:

```text
setting -> individual setting update/delete events
group   -> group update/delete events
both    -> all events
```

Keep no-op recorder as default.

Add tests for all modes.

---

# Phase 10 — Recursive Secret Redaction

Current whole-root redaction is safe but loses non-secret context.

Instead of:

```text
[encrypted]
```

for the entire object, prefer:

```php
[
    'host' => 'smtp.example.com',
    'port' => 587,
    'password' => '[encrypted]',
]
```

Implement recursive redaction:

```php
private function redactEncryptedValues(mixed $value): mixed
{
    if ($this->isEncrypted($value)) {
        return self::REDACTED;
    }

    if (! is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $nested) {
        $value[$key] = $this->redactEncryptedValues($nested);
    }

    return $value;
}
```

Use for event-safe and audit-safe payloads.

## Tests

- Root secret redacted.
- Nested secret redacted.
- Multiple nested secrets redacted.
- Non-secret siblings preserved.
- Delete events safe.
- Audit entries safe.

---

# Phase 11 — Strengthen Identifier Validation

Keep existing `SettingKey` validation.

Also review:

```text
scope
group
root key
nested path
```

for:

```text
empty values
control characters
unreasonable length
invalid separators
```

Do not over-restrict legitimate names.

Align DB column lengths with explicit migration decisions.

If changing schema:

- use a new upgrade migration
- preserve custom `settings.table_name`
- support SQLite tests
- avoid MySQL index-length issues
- avoid breaking PostgreSQL

Do not casually rewrite published historical migrations.

---

# Phase 12 — CI and Release Verification

Final advertised matrix must match actual verified results.

Expected target:

```text
PHP      8.3+
Laravel  11.28+, 12.x, 13.x when verified
Filament 4.x, 5.x
Livewire 3.x for Filament 4
Livewire 4.x for Filament 5
```

CI must include:

```text
normal latest-compatible matrix
prefer-lowest jobs
composer validate --strict
composer format:test
composer analyse
composer test
```

In the final report distinguish:

```text
workflow configured
```

from:

```text
workflow actually passed
```

---

# Required Regression Coverage

Ensure equivalent coverage exists for:

```text
Security
- SecretStateAfterSave
- EncryptedFormMount
- EncryptedFormSave
- RecursiveRedaction

Authorization
- PluginAuthorization
- DefinitionAuthorization
- DirectRouteAuthorization
- GenericPageRegistration
- MultiPanelIsolation

Compatibility
- ComposerConstraint
- PreferLowest
- Filament4Smoke
- Filament5Smoke

Lifecycle
- AfterSave
- AfterCommit
- Rollback

Concurrency
- NestedMutation

Casting
- StringCast
- IntegerCast
- FloatCast
- BooleanCast
- ArrayCast

Audit
- AuditGranularity
- AuditSecretRedaction
```

Do not duplicate tests when equivalent coverage already exists.

---

# Definition of Done

Complete only when:

- Plaintext secrets do not remain in Livewire/public page state after save.
- Form secret fields are blank after successful save.
- Existing secret preservation still works.
- Same plaintext does not cause false updates.
- Authorization contract for generic pages is explicit and secure.
- Definition access cannot be mistaken for universal route protection.
- Laravel 11 lower bound is accurate.
- prefer-lowest CI exists and passes for declared lower bounds.
- `afterCommit()` exists and is tested.
- Nested writes use safer latest-state mutation or documented fallback.
- Definition cast semantics are consistent and tested.
- PHP wording matches Composer semantics.
- Audit granularity is implemented or explicitly deferred.
- Recursive secret redaction is implemented or explicitly deferred.
- Public APIs remain backward compatible.
- Pint passes.
- PHPStan passes.
- Pest passes.
- Advertised compatibility equals verified compatibility.

---

# Final Report Format

Report:

```text
1. Baseline status
2. Security fixes
3. Authorization changes
4. Compatibility changes
5. prefer-lowest verification
6. Lifecycle changes
7. Concurrency changes
8. Casting changes
9. Audit/redaction changes
10. Public API impact
11. Tests added/updated
12. Files changed
13. Verified compatibility matrix
14. Deferred work
15. Remaining risks
```

Be explicit about anything not implemented or not verified.
