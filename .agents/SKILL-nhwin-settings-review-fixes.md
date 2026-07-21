---
name: nhwin-settings-review-fixes
description: >
  Apply post-review fixes and production hardening to the nhwin304/settings package.
  Focus on page-level authorization, Octane-safe scope resolution, encrypted secret update
  semantics, compatibility consistency, strict casting, delete APIs, auditability, and tests.
---

# nhwin/settings Review Fixes Skill

## Scope

Use this skill on `nhwin304/settings` after the major modernization work is already present.

Do not rewrite the architecture from scratch. The repository already contains:

- `SettingsManagerContract`
- `SettingsManager`
- `SettingsRepository`
- `DatabaseSettingsRepository`
- group-level cache
- scope support
- `setMany()`
- nested settings
- encryption
- typed getters
- optional definitions
- events
- `AbstractPageSettings`
- `SettingsPageAdapter`
- `SettingsPlugin`
- `SettingsRegistry`
- `SettingsPageDefinition`
- Settings Hub
- generator presets
- Filament 4/5 compatibility work
- CI compatibility matrix

The goal is to fix the remaining correctness, security, lifecycle, and compatibility issues found
during review.

---

# Non-Negotiable Backward Compatibility

Preserve these public APIs:

```php
settings('general.site_name');
settings('general.site_name', 'Default');
settings()->setMany('general', [...]);

Setting::get('general.site_name');
Setting::set('general.site_name', 'value');
Setting::setMany('general', [...]);
Setting::setEncrypted('mail.smtp.password', 'secret');
Setting::getGroup('general');
Setting::getGroupLastUpdatedAt('general');
Setting::forScope('tenant:1')->get('general.site_name');
```

Preserve Blade:

```blade
@settings('general.site_name', 'Default')
```

Preserve support for:

```text
PHP 8.3+
Filament 4
Filament 5
```

Do not commit, push, tag, publish, or create a PR unless explicitly requested.

After every phase run:

```bash
composer format:test
composer analyse
composer test
```

Do not continue with knowingly broken tests.

---

# Priority Order

```text
P0  Fix SettingsPageDefinition authorization bypass
P1  Make scope resolution safe for Octane / long-running workers
P1  Fix encrypted field update semantics
P1  Align Composer, README, and CI compatibility declarations
P2  Fix strict boolean casting
P2  Improve float getter behavior
P2  Add forget/delete APIs
P3  Add optional audit trail foundation
P3  Expand regression and compatibility tests
```

---

# Phase 0 — Baseline Audit

Before modifying code:

1. Inspect:
   - `SettingsManager`
   - `SettingsManagerContract`
   - `SettingsPlugin`
   - `SettingsRegistry`
   - `SettingsPageDefinition`
   - `SettingsHub`
   - `AbstractPageSettings`
   - `SettingServiceProvider`
   - scope resolver classes
   - definitions
   - encryption tests
   - CI matrix
   - `composer.json`
   - README compatibility table

2. Run:

```bash
composer validate --strict
composer format:test
composer analyse
composer test
```

3. Record baseline failures separately from regressions introduced by this work.

---

# Phase 1 — P0: Enforce Page-Level Authorization

## Problem

`SettingsPageDefinition::canAccess()` currently controls visibility in the Settings Hub, but a
registered page may still be reachable by direct route because page registration uses all page
classes and `AbstractPageSettings::canAccess()` only checks plugin-level access.

Potential failure:

```text
Definition access = false
        |
        +-- hidden in Settings Hub
        |
        +-- direct page route still accessible
```

## Required behavior

When a registered page definition has:

```php
->canAccess(fn (): bool => false)
```

then all of the following must be true:

```text
Hub card hidden           YES
Navigation hidden         YES where supported
Direct route access       DENIED
Page canAccess()           false
```

Final access must conceptually be:

```text
plugin access
AND
definition access
AND
page native/custom access
```

Avoid recursive calls between:

```text
SettingsPageDefinition::isAccessible()
AbstractPageSettings::canAccess()
```

## Recommended design

Add registry lookup:

```php
public function findByPage(string $pageClass): ?SettingsPageDefinition;
```

Separate callback evaluation from page access.

Example:

```php
public function passesAccessCallback(): bool
{
    return $this->access === null || (bool) ($this->access)();
}
```

Then `AbstractPageSettings::canAccess()` should:

1. Resolve current panel.
2. Check plugin-level access.
3. Look up the current page definition.
4. Check definition callback access.
5. Respect any additional page-native access logic without recursion.

Do not use a method that calls `$pageClass::canAccess()` from inside the same definition lookup
path if that would recurse.

## Required tests

Add tests proving:

1. Plugin access false blocks all settings pages.
2. Definition access false hides Hub item.
3. Definition access false blocks direct page access.
4. Definition access true permits access when plugin access is true.
5. Different definitions can have different rules.
6. Registry access does not leak between panels.
7. No recursion occurs.

Treat this as a security regression fix.

---

# Phase 2 — P1: Octane-Safe Scope Resolution

## Problem

The manager is currently effectively long-lived and resolves default scope during construction.

This can become unsafe with:

```text
Laravel Octane
Swoole
RoadRunner
long-running workers
```

Example:

```text
Request A -> tenant:1
singleton manager resolves tenant:1

Request B -> tenant:2
same manager instance may still use tenant:1
```

## Required design

Use one of these safe patterns.

### Preferred A — scoped container binding

```php
$this->app->scoped(
    SettingsManagerContract::class,
    SettingsManager::class,
);
```

### Preferred B — lazy scope resolution

Store only explicit override:

```php
protected ?string $explicitScope = null;
```

Resolve default scope on operation:

```php
protected function currentScope(): string
{
    return $this->explicitScope ?? $this->scopeResolver->resolve();
}
```

`forScope()` returns a clone with an explicit override.

### Acceptable

Use both scoped binding and lazy resolution.

## Required behavior

```php
Setting::get(...)
```

uses the resolver's current scope.

```php
Setting::forScope('tenant:15')->get(...)
```

always uses `tenant:15`.

Calling `forScope()` must not mutate the shared/default manager.

## Required tests

Simulate:

```text
resolver -> tenant:1
write/read

resolver -> tenant:2
write/read
```

Ensure no leakage.

Also test explicit scopes:

```php
$tenant1 = Setting::forScope('tenant:1');
$tenant2 = Setting::forScope('tenant:2');
```

and verify isolation.

Add a long-running-container regression-style test by reusing the same container while changing
resolver output.

Document Octane safety after the fix.

---

# Phase 3 — P1: Fix Encrypted Secret Update Semantics

## Problem

Definition-based encrypted values can be decrypted for use, then encrypted again on every
`setMany()` save.

Because Laravel encryption is non-deterministic:

```text
same plaintext
!=
same ciphertext
```

this can cause false changes:

```text
unnecessary DB update
unnecessary cache invalidation
unnecessary events
new ciphertext every save
```

## Required secret semantics

```text
secret omitted
-> preserve existing encrypted value

blank secret field
-> preserve existing encrypted value by default

new non-empty secret
-> encrypt and replace

explicit clear action
-> remove only when intentionally requested
```

Do not require consumers to resubmit existing plaintext merely to preserve it.

## Recommended approach

For definition encrypted paths:

1. Load raw stored group.
2. Detect encrypted paths.
3. If incoming path is missing, preserve raw encrypted value.
4. If incoming path is blank and field semantics are "preserve blank", preserve raw encrypted value.
5. If incoming path has a new value, encrypt once and replace.
6. Compare final raw values against previous raw values before deciding a change occurred.

Do not expose decrypted secrets through:

```text
logs
exceptions
events
audit records
debug output
```

## Filament guidance

Generated secret/password fields should usually not be filled with the real secret.

Use a Filament 4/5-compatible pattern where blank input is not dehydrated or is otherwise treated
as "keep existing".

## Required tests

1. Saving unrelated fields does not change encrypted ciphertext.
2. Blank secret preserves existing ciphertext.
3. New secret changes ciphertext and returned plaintext.
4. Unchanged secret does not dispatch update events.
5. Explicit clear is intentional and tested.
6. Secret values remain redacted in events.
7. Nested encrypted siblings remain intact.

---

# Phase 4 — P1: Align Compatibility Metadata

## Problem

Compatibility declarations must match across:

```text
composer.json
README
CI matrix
```

Do not advertise untested combinations.

## Target

Unless explicitly changed by the repository owner, normalize to:

```text
PHP      ^8.3
Laravel  ^11.28 | ^12.0
Filament ^4.0 | ^5.0
```

Recommended Composer:

```json
{
  "require": {
    "php": "^8.3",
    "laravel/framework": "^11.28|^12.0",
    "filament/filament": "^4.0|^5.0"
  }
}
```

Do not claim Laravel 13 until all of the following exist:

```text
Composer resolution
correct Testbench version
Filament combination resolution
Pint pass
PHPStan pass
Pest pass
CI matrix entry
README documentation
```

## Required CI combinations

Verify all resolvable combinations:

```text
PHP 8.3 + Laravel 11.28 + Filament 4
PHP 8.3 + Laravel 11.28 + Filament 5
PHP 8.3 + Laravel 12    + Filament 4
PHP 8.3 + Laravel 12    + Filament 5
PHP 8.4 + Laravel 12    + Filament 4
PHP 8.4 + Laravel 12    + Filament 5
```

Synchronize README and Composer with what actually passes.

---

# Phase 5 — P2: Strict Boolean Casting

## Problem

This is unsafe:

```php
(bool) $value
```

because:

```php
(bool) 'false' === true;
```

## Required behavior

Boolean casting must be deterministic.

Accept at minimum:

```text
true
false
1
0
"1"
"0"
```

Optionally support:

```text
"true"
"false"
"yes"
"no"
"on"
"off"
```

but document exact behavior.

Reject ambiguous values rather than silently coercing them.

## Recommended implementation

Create a dedicated caster method/class.

Use:

```php
filter_var(
    $value,
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE,
);
```

with explicit error handling.

Throw a meaningful cast exception for invalid values.

Never include sensitive values in the exception message.

## Required tests

```text
true       -> true
false      -> false
1          -> true
0          -> false
"1"        -> true
"0"        -> false
"true"     -> true if supported
"false"    -> false if supported
"random"   -> exception
```

---

# Phase 6 — P2: Improve float() Getter

## Problem

JSON number:

```json
5
```

decodes to PHP integer, so strict `is_float()` rejects valid numeric data.

## Required behavior

```php
Setting::float('payment.fee');
```

must accept:

```text
int
float
```

and return:

```php
(float) $value
```

Recommended semantics:

```text
5       -> 5.0
5.5     -> 5.5
"5.5"   -> exception
```

Do not accept arbitrary numeric strings unless explicitly documented.

Add tests for integer, float, invalid string, and default behavior.

---

# Phase 7 — P2: Add Forget/Delete APIs

## Problem

Removed fields can leave stale rows forever.

## Required public APIs

Add:

```php
Setting::forget('general.legacy_key');
Setting::forgetGroup('general');
```

Manager contract:

```php
public function forget(string $key): void;

public function forgetGroup(string $group): void;
```

Repository contract:

```php
public function forget(
    string $scope,
    string $group,
    string $key
): void;

public function forgetGroup(
    string $scope,
    string $group
): void;
```

## Nested delete semantics

```php
Setting::forget('general.social.facebook');
```

must remove only `social.facebook` while preserving siblings.

If the resulting root structure is empty, prefer deleting the root DB row unless there is a
documented reason to keep an empty value.

## Transaction/cache/events

Deletion must:

```text
run in transaction
invalidate cache after commit
dispatch deletion-aware events
```

Use either dedicated events:

```text
SettingDeleted
SettingsGroupDeleted
```

or explicit deleted semantics in existing events.

Never expose encrypted old values.

## Required tests

1. Forget root key.
2. Forget nested key.
3. Preserve nested siblings.
4. Forget group.
5. Scope isolation.
6. Cache invalidation.
7. Events.
8. Encrypted deletion redaction.

---

# Phase 8 — P3: Optional Audit Trail Foundation

Audit must be optional.

Recommended architecture:

```text
domain settings events
        |
        v
optional audit listener
        |
        v
SettingsAuditRecorder contract
```

Possible payload:

```text
actor_id
actor_type
scope
group
key
action
old_value
new_value
created_at
```

Secrets must always be redacted or omitted.

Do not add audit columns to the core settings table.

Prefer a replaceable contract with a no-op default.

A full audit table may be deferred, but events must be safe enough for external audit consumers.

---

# Phase 9 — Registry Improvements

Add:

```php
public function findByPage(string $pageClass): ?SettingsPageDefinition;
```

Ensure duplicate page registration is deterministic.

Recommended behavior:

```text
same page registered twice
-> latest definition replaces earlier definition
```

External URL entries must not appear in `pageClasses()`.

Add tests.

---

# Phase 10 — Filament 4/5 Compatibility Hardening

Keep version-sensitive behavior inside the compatibility layer.

Do not spread major-version checks across page classes.

Verify under Filament 4 and 5:

```text
Page lifecycle
schema/form fill
getState
saveRelationships
navigation
Settings Hub rendering
plugin registration
page authorization
generator output
unsaved data changes
database transactions
```

Add compatibility smoke tests where practical.

---

# Phase 11 — Documentation

Update README to document:

## Authorization

```text
Plugin access
Definition access
Direct route protection
```

## Scope

State clearly whether Octane and long-running workers are safe.

## Secrets

Document:

```text
blank -> preserve
new value -> replace
explicit clear -> clear
```

## Delete APIs

Document:

```php
Setting::forget(...);
Setting::forgetGroup(...);
```

## Compatibility

README must exactly match Composer and CI.

Do not claim unverified versions.

---

# Required Regression Coverage

Ensure equivalent coverage exists for:

```text
Authorization
- Plugin access
- Definition access
- Direct route access
- Multi-panel isolation

Scope
- Dynamic resolver
- Explicit scope
- Scope isolation
- Long-running container behavior

Encryption
- Preserve unchanged secret
- Replace secret
- Clear secret
- Event redaction
- Encrypted sibling preservation

Casting
- Boolean cast
- Float getter

Deletion
- Forget root
- Forget nested
- Forget group
- Cache invalidation
- Delete events

Compatibility
- Composer constraints
- Filament 4 smoke test
- Filament 5 smoke test
```

Do not duplicate test files when equivalent coverage already exists.

---

# Definition of Done

The work is complete only when:

- Definition-level `canAccess()` cannot be bypassed by direct route.
- Plugin-level and page-level access both work.
- No authorization recursion exists.
- Scope resolution is safe for long-running containers.
- `forScope()` remains isolated.
- Unchanged encrypted secrets are not re-encrypted.
- Blank secret fields preserve existing values as documented.
- Explicit secret replacement works.
- Explicit secret clear works.
- Composer, README, and CI advertise the same compatibility.
- PHP 8.3+ is verified.
- Filament 4 is verified.
- Filament 5 is verified.
- Boolean cast handles `"false"` correctly.
- Float getter accepts integer JSON values.
- `forget()` works.
- `forgetGroup()` works.
- Nested delete preserves siblings.
- Cache invalidates only after successful commits.
- Secret values remain redacted in all events.
- Full tests pass.
- PHPStan passes.
- Pint passes.
- README matches implementation.

---

# Final Report Format

After completing the work, report:

```text
1. Baseline status
2. P0 fixes completed
3. P1 fixes completed
4. P2 fixes completed
5. P3 work completed or deferred
6. Authorization behavior after fix
7. Scope / Octane safety status
8. Encryption behavior after fix
9. Compatibility matrix verified
10. New public APIs
11. Tests added or updated
12. Files changed
13. Remaining risks
```

Be explicit about anything deferred or not verified.
