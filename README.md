# Nhwin Settings

Gói quản lý cấu hình động cho Laravel, lưu dữ liệu theo `scope + group + key` dưới dạng JSON, có cache theo nhóm và tích hợp tùy chọn với Filament.

## Tương thích

Constraint phát hành:

| Thành phần | Phiên bản hỗ trợ |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 11.28+, 12.x hoặc 13.x |
| Filament | 4.x hoặc 5.x |
| Livewire | 3.x với Filament 4; 4.x với Filament 5 |

Các tổ hợp đã được chạy đầy đủ tại máy phát triển:

| PHP | Laravel | Filament | Livewire | Kết quả |
| --- | --- | --- | --- | --- |
| 8.3.6 | 11.39.1 | 4.0.0 | 3.5.0 | `prefer-lowest`, Composer, Pint, PHPStan và Pest đều đạt |
| 8.3.6 | 11.39.1 | 5.0.0 | 4.1.0 | `prefer-lowest`, Composer, Pint, PHPStan và Pest đều đạt |
| 8.3.6 | 12.64.0 | 4.12.1 | 3.8.2 | Composer, Pint, PHPStan và Pest đều đạt |
| 8.3.6 | 12.64.0 | 5.7.1 | 4.3.3 | Composer, Pint, PHPStan và Pest đều đạt |
| 8.3.6 | 13.21.1 | 4.12.1 | 3.8.2 | Composer, Pint, PHPStan và Pest đều đạt |
| 8.3.6 | 13.21.1 | 5.7.1 | 4.3.3 | Composer, Pint, PHPStan và Pest đều đạt |

Constraint Composer cho phép PHP 8.3 trở lên. Workflow CI hiện cấu hình PHP 8.3/8.4; chỉ các tổ hợp liệt kê trong bảng trên mới được xác minh đầy đủ tại máy phát triển. Workflow còn có các job `prefer-lowest` riêng cho lower bound Laravel 11.28 với Filament 4/5. Không cần khai báo trực tiếp Livewire trong ứng dụng; Filament sẽ chọn major phù hợp.

## Cài đặt

```bash
composer require nhwin/settings
```

Xuất config và migration, sau đó chạy migration:

```bash
php artisan vendor:publish --tag=settings-config
php artisan vendor:publish --tag=settings-migrations
php artisan vendor:publish --tag=settings-translations
php artisan migrate
```

Hoặc dùng lệnh cài đặt của package:

```bash
php artisan settings:install
```

Tên bảng mặc định là `settings`. Có thể đổi `table_name` trong `config/settings.php` trước khi migrate.

## Đọc và ghi cơ bản

Helper cũ được giữ nguyên:

```php
$name = settings('general.site_name');
$name = settings('general.site_name', 'Tên mặc định');
```

Facade cũng giữ toàn bộ API cũ:

```php
use Nhwin\Settings\Facades\Setting;

Setting::set('general.site_name', 'Nhwin');

$name = Setting::get('general.site_name');
$group = Setting::getGroup('general');
$updatedAt = Setting::getGroupLastUpdatedAt('general');
```

Trong Blade, directive sẽ escape HTML giống `{{ }}`:

```blade
<h1>@settings('general.site_name', 'Tên mặc định')</h1>
```

Gọi `settings()` không truyền khóa để lấy manager:

```php
settings()->setMany('general', [
    'site_name' => 'Nhwin',
    'maintenance' => false,
]);
```

## Dữ liệu lồng nhau

Phần đầu là group, phần thứ hai là root key, các phần còn lại là đường dẫn lồng nhau:

```php
Setting::set('general.social', [
    'facebook' => 'https://facebook.com/nhwin',
    'github' => 'https://github.com/nhwin304',
]);

Setting::set('general.social.facebook', 'https://facebook.com/new-page');

$facebook = Setting::get('general.social.facebook');
$github = Setting::get('general.social.github'); // vẫn được giữ nguyên
```

Giá trị `null` đã lưu sẽ trả về `null`; default chỉ được dùng khi đường dẫn không tồn tại.

Database repository mặc định thực hiện nested read-modify-write trong transaction và khóa root row,
nên sibling mutations dựa trên state committed mới nhất. Custom repository chỉ implement
`SettingsRepository` vẫn tương thích và dùng fallback cũ; implement thêm
`AtomicSettingsRepository` để cung cấp cùng bảo đảm concurrency.

## Ghi hàng loạt

`setMany()` thực hiện một transaction, một lần `upsert()` cho cả group và một lần xóa cache sau commit:

```php
Setting::setMany('general', [
    'site_name' => 'Nhwin',
    'maintenance' => false,
    'social' => [
        'facebook' => 'https://facebook.com/nhwin',
    ],
]);
```

Trang Filament cũng dùng API này, không gọi `set()` lặp lại cho từng field.

## Cache

Cache được lưu theo toàn bộ group với khóa:

```text
{prefix}:{scope}:{group}
settings:global:general
```

Cấu hình trong `config/settings.php`:

```php
'cache' => [
    'prefix' => 'settings',
    'ttl' => null,
],
```

`ttl` tính theo phút. `null` hoặc `0` nghĩa là cache vĩnh viễn. Cache chỉ bị vô hiệu hóa sau khi transaction ghi DB commit thành công; rollback không làm lộ dữ liệu dở dang.

## Getter kiểm tra kiểu

```php
Setting::string('general.site_name');
Setting::integer('limits.upload_mb');
Setting::float('payment.fee');
Setting::boolean('general.maintenance');
Setting::array('general.social');
Setting::collection('general.social');
```

Nếu kiểu đã lưu không đúng, package ném `Nhwin\Settings\Exceptions\InvalidSettingType` và không đưa giá trị nhạy cảm vào thông báo lỗi.

`float()` nhận số JSON kiểu integer hoặc float rồi luôn trả về `float`; chuỗi số như `"5.5"` bị từ chối. Boolean cast trong definition nhận `true`, `false`, `1`, `0` và các chuỗi không phân biệt hoa thường `"1"`, `"0"`, `"true"`, `"false"`, `"yes"`, `"no"`, `"on"`, `"off"`; giá trị mơ hồ bị từ chối.

## Definition tùy chọn

Definition casts chuẩn hóa và kiểm tra kiểu của giá trị group đã lưu: string chỉ nhận string,
integer chỉ nhận int, float nhận int/float rồi trả float, boolean dùng tập biểu diễn rõ ràng,
và array chỉ nhận array. Typed getters kiểm tra kiểu tại thời điểm application code yêu cầu
một kiểu cụ thể; chúng không thay thế definition normalization.

Settings động vẫn là mặc định. Khi cần default, cast và danh sách mã hóa, tạo definition:

```php
namespace App\Settings;

use Nhwin\Settings\Definitions\SettingsDefinition;

final class GeneralSettingsDefinition extends SettingsDefinition
{
    public static function group(): string
    {
        return 'general';
    }

    public function defaults(): array
    {
        return ['site_name' => '', 'maintenance' => false];
    }

    public function casts(): array
    {
        return ['site_name' => 'string', 'maintenance' => 'boolean'];
    }

    public function encrypted(): array
    {
        return ['api.token'];
    }
}
```

Đăng ký trong `config/settings.php`:

```php
'definitions' => [
    App\Settings\GeneralSettingsDefinition::class,
],
```

## Mã hóa secret

Mã hóa một giá trị cụ thể bằng Laravel Encrypter:

```php
Setting::setEncrypted('mail.smtp.password', $password);
$password = Setting::get('mail.smtp.password');
```

DB chỉ chứa ciphertext. Với path được khai báo trong `SettingsDefinition::encrypted()`:

- Không gửi field secret hoặc gửi chuỗi trống/`null`: giữ nguyên ciphertext hiện có.
- Gửi lại đúng plaintext hiện có: giữ nguyên ciphertext và không phát update event giả.
- Gửi plaintext mới: mã hóa một lần và thay thế secret.
- Xóa có chủ đích: dùng `Setting::clearEncrypted('mail.smtp.password')` để clear thành `null`, hoặc `Setting::forget('mail.smtp.password')` để xóa path.

Form Filament được fill bằng giá trị trống thay vì plaintext secret; password field do generator tạo chỉ dehydrate khi có input mới. Payload hỏng sẽ ném `DecryptException`; events và audit entries thay đệ quy từng secret bằng `[encrypted]` nhưng vẫn giữ các sibling không nhạy cảm.

## Tạo trang Filament

Lệnh tối thiểu:

```bash
php artisan make:settings General
```

Các tùy chọn:

```bash
php artisan make:settings General \
    --panel=super-admin \
    --group=general \
    --preset=general \
    --typed \
    --hub \
    --force \
    --no-interaction
```

- `--panel`: hỗ trợ ID như `admin`, `super-admin`, `author_panel`.
- `--group`: group lưu trong DB.
- `--preset`: `general`, `seo`, `mail`, `social`, `analytics`.
- `--typed`: sinh thêm `app/Settings/*SettingsDefinition.php`.
- `--hub`: thêm metadata để registry dùng trong Settings Hub.
- `--force`: cho phép ghi đè file đã có.
- `--no-interaction`: dùng trong CI; bắt buộc truyền tên page.

Page sinh ra tương thích Filament 4/5 và kế thừa `AbstractPageSettings`. Base page hỗ trợ `beforeFill`, `afterFill`, `beforeValidate`, `afterValidate`, `beforeSave`, `afterSave`, `afterCommit`, hai hook mutate, transaction, unsaved-change alert và phím `Ctrl/Cmd + S`. `afterSave` chạy bên trong transaction; `afterCommit` chạy sau commit và có thể đọc state đã commit.

```php
protected function mutateFormDataBeforeSave(array $data): array
{
    $data['saved_by'] = auth()->id();

    return $data;
}
```

## Settings Hub

Internal pages passed to `pages()` or `SettingsPageDefinition::make()` must extend
`AbstractPageSettings`, so plugin, definition, and direct-route authorization are always applied.
Use `SettingsPageDefinition::external()` for destinations whose authorization is managed elsewhere.

Đăng ký plugin trong Panel Provider:

```php
use App\Filament\Admin\Pages\GeneralSettings;
use Nhwin\Settings\Filament\Plugins\SettingsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(
        SettingsPlugin::make()
            ->pages([GeneralSettings::class])
            ->hub()
    );
}
```

Có thể khai báo metadata thủ công:

```php
use Nhwin\Settings\Filament\SettingsPageDefinition;

SettingsPageDefinition::make(GeneralSettings::class)
    ->label('Cấu hình chung')
    ->description('Nhận diện và giá trị mặc định')
    ->icon('heroicon-o-cog-6-tooth')
    ->group('Hệ thống')
    ->sort(1)
    ->badge('Mới')
    ->canAccess(fn (): bool => auth()->user()?->can('settings.view') ?? false);
```

Đích ngoài Filament cũng được hỗ trợ:

```php
SettingsPageDefinition::external('/profile/settings')->label('Hồ sơ');
```

Class cũ `SettingPlugin` vẫn hoạt động nhưng đã deprecated; nên chuyển sang `SettingsPlugin`.

## Quyền truy cập và nhiều panel

Không bắt buộc Filament Shield:

```php
SettingsPlugin::make()
    ->pages([GeneralSettings::class])
    ->hub()
    ->canAccess(fn (): bool => auth()->user()?->can('settings.view') ?? false);
```

Quyền cuối cùng là giao của ba lớp: callback plugin, callback trên `SettingsPageDefinition`, và hook native của page. Khi một definition trả về `false`, card trong Hub và navigation bị ẩn, đồng thời direct route bị chặn qua `AbstractPageSettings::canAccess()`.

Để thêm rule riêng cho page mà vẫn giữ toàn bộ lớp bảo vệ, override hook sau thay vì thay thế `canAccess()`:

```php
protected static function canAccessSettingsPage(): bool
{
    return auth()->user()?->can('settings.general') ?? false;
}
```

Mỗi panel phải có plugin instance riêng; registry không rò cấu hình giữa panel:

```php
$adminPlugin = SettingsPlugin::make()
    ->pages([GeneralSettings::class, SeoSettings::class])
    ->hub();

$authorPlugin = SettingsPlugin::make()
    ->pages([AuthorSettings::class])
    ->hub();
```

## Scope và multi-tenancy

Scope mặc định là `global`:

```php
Setting::forScope('tenant:15')->set('general.site_name', 'Tenant 15');

$name = Setting::forScope('tenant:15')->get('general.site_name');
```

Không hard-code `tenant_id`. Có thể thay resolver:

```php
use Nhwin\Settings\Contracts\ScopeResolver;

final class TenantScopeResolver implements ScopeResolver
{
    public function resolve(): string
    {
        return auth()->check() ? 'tenant:'.auth()->user()->tenant_id : 'global';
    }
}
```

```php
'scope_resolver' => App\Settings\TenantScopeResolver::class,
```

Manager dùng scoped container binding và resolve default scope lười ở từng operation. Vì vậy cùng một Octane/Swoole/RoadRunner worker có thể chuyển tenant giữa các request mà không giữ scope cũ. `forScope()` luôn trả clone có explicit scope và không mutate manager mặc định.

## Xóa setting

```php
Setting::forget('general.legacy_key');
Setting::forget('general.social.facebook');
Setting::forgetGroup('general');
```

Xóa nested chỉ bỏ đúng path và giữ sibling; root row được xóa khi cấu trúc còn lại rỗng. Xóa luôn chạy trong transaction, phân tách theo scope và chỉ invalidate cache/phát event sau commit thành công.

## Events

Sau commit thành công, package phát:

```php
Nhwin\Settings\Events\SettingUpdated::class
Nhwin\Settings\Events\SettingsGroupUpdated::class
Nhwin\Settings\Events\SettingDeleted::class
Nhwin\Settings\Events\SettingsGroupDeleted::class
```

Event chứa `scope`, `group`, key thay đổi và old/new value an toàn. Hãy xử lý việc reload mail config, xóa theme cache hoặc tạo sitemap trong listener của ứng dụng, không đặt side effect vào core package.

Các identifier `scope`, `group` và root key phải khác rỗng, không chứa control character và tối đa 255 ký tự để khớp các cột `string` hiện tại; `group`/root key không nhận dấu chấm vì dấu chấm dành cho phân tách setting path. Mỗi nested segment có cùng giới hạn và toàn bộ setting key tối đa 1024 ký tự.

Audit trail là tùy chọn và không thêm cột vào bảng `settings`. Mặc định `Nhwin\Settings\Contracts\SettingsAuditRecorder` được bind vào recorder no-op. Ứng dụng có thể bind contract này vào implementation riêng để nhận `SettingsAuditEntry`; actor mặc định là `null` và có thể được bổ sung bởi recorder của ứng dụng. `settings.audit.granularity` nhận `setting` (mặc định, tránh record trùng khi bulk update), `group` hoặc `both`. Mọi secret trong entry vẫn được redact.

## Nâng cấp từ phiên bản cũ

1. Dùng PHP 8.3+ và chọn Laravel 11/12/13 tương thích.
2. Chạy `composer update nhwin/settings filament/filament --with-all-dependencies`.
3. Publish migration mới và chạy `php artisan migrate`. Migration thêm `scope = global` cho dữ liệu hiện hữu và đổi unique key thành `(scope, group, key)`.
4. Không cần đổi `settings()`, facade `Setting`, `@settings`, `getGroup()` hoặc `getGroupLastUpdatedAt()`.
5. Page cũ chỉ cần sửa import sai lịch sử thành `Nhwin\Settings\Abstracts\AbstractPageSettings` nếu từng được sinh bởi bản lỗi.
6. Đổi `Nhwin\Settings\Filament\Plugins\SettingPlugin` sang `SettingsPlugin` khi thuận tiện.
7. Kiểm tra mọi code từng dựa vào hành vi nested set cũ; nested set nay chỉ cập nhật đúng nhánh và giữ sibling.

## Phát triển và kiểm thử

```bash
composer validate --strict
composer format:test
composer analyse
composer test

# Chạy cả formatter, PHPStan và Pest
composer check
```

CI được cấu hình cho ma trận PHP 8.3/8.4, Laravel 11/12/13 và Filament 4/5. Filament 5 được ghép với Livewire 4.1+ trong job tương ứng. Cấu hình workflow không được xem là bằng chứng một tổ hợp đã pass nếu chưa có lần chạy thành công.

## Giấy phép

MIT. Xem [LICENSE](LICENSE).
