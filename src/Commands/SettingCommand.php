<?php

namespace Nhwin304\Settings\Commands;

use Filament\Facades\Filament;                         // UPDATED: import Filament
use Illuminate\Console\Attributes\AsCommand;           // UPDATED: import attribute
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;                  // UPDATED: import Filesystem
use Illuminate\Support\Pluralizer;                     // UPDATED: import Pluralizer
use Illuminate\Support\Str;                            // UPDATED: import Str
use function Laravel\Prompts\suggest;                  // UPDATED: import prompt helper
use function Laravel\Prompts\text;                     // UPDATED: import prompt helper

#[AsCommand(
    name: 'make:settings',
    aliases: ['settings']
)]
/**
 * Tạo nhanh **Filament Settings Page** + **Blade view** từ stub.
 *
 * ## Mô tả
 * Lệnh sẽ sinh:
 * - Page class: `app/Filament/{Panel?}/Pages/{Name}Settings.php`
 * - View: `resources/views/filament/pages/{name-kebab}-settings.blade.php`
 *
 * ## Cách dùng
 * - Tương tác (khuyến nghị):
 *   `php artisan make:settings`
 *   → nhập *Name* (vd: Site, General, Mail) và chọn *Panel* (nếu có nhiều panel).
 *
 * - Truyền tham số trực tiếp:
 *   `php artisan make:settings General admin`
 *   → tạo `app/Filament/Admin/Pages/GeneralSettings.php`
 *   và `resources/views/filament/pages/general-settings.blade.php`.
 *
 * - Không ghi đè file đã tồn tại. Sử dụng lại stub tại `stubs/page.stub` & `stubs/view.stub`.
 *
 * ## Tham số
 * - `{name?}`  : Tên settings (không cần kèm 'Settings', lệnh sẽ tự bỏ hậu tố).
 * - `{panel?}` : Tên panel Filament (vd: `admin`). Bỏ trống = panel mặc định.
 *
 * ## Notes
 * - Hỗ trợ `--no-interaction` (CI). Khi đó **name** là bắt buộc.
 * - Panel list lấy từ `Filament::getPanels()`.
 */
class SettingCommand extends Command
{
    /**
     * Tên & chữ ký console command.
     *
     * Ví dụ: `php artisan make:settings {name?} {panel?}`
     *
     * @var string
     */
    protected $signature = 'make:settings {name?} {panel?}';

    /**
     * Mô tả ngắn gọn hiển thị trong `php artisan list`.
     *
     * @var string
     */
    protected $description = 'Create a Filament Settings Page class and its Blade view from stubs.';

    /**
     * Alias bổ sung (ngoài AsCommand aliases).
     *
     * @var array<string>
     */
    protected $aliases = ['settings'];

    /** Filesystem instance */
    protected Filesystem $files;

    /** Cached name argument */
    protected ?string $cachedName = null;

    /** Cached panel argument */
    protected ?string $cachedPanel = null;

    /** Whether panel argument has been cached */
    protected bool $panelCached = false;

    /**
     * Inject Filesystem qua container.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Thực thi lệnh.
     *
     * - Hỏi/đọc tham số `name`, `panel`
     * - Sinh nội dung từ `page.stub`
     * - Tạo view từ `view.stub`
     * - Ghi file nếu chưa tồn tại
     *
     * @return int 0 (SUCCESS) | 1 (FAILURE)
     */
    public function handle(): int
    {
        $name  = $this->getNameArgument();
        $panel = $this->getPanelArgument();

        $path = $this->getSourceFilePath();

        $this->makeDirectory(\dirname($path));

        $contents = $this->getSourceFile();

        $this->createViewFromStub(
            'filament.pages.' . Str::of($name)->headline()->lower()->slug() . '-settings'
        );

        if ($contents === false) {
            $this->warn("Could not build source file contents for {$path}");

            return self::FAILURE; // UPDATED
        }

        if (! $this->files->exists($path)) {
            $this->files->put($path, $contents);
            $this->info("File : {$path} created");
        } else {
            $this->warn("File : {$path} already exists"); // UPDATED: fix typo
        }

        return self::SUCCESS; // UPDATED
    }

    /**
     * Lấy tham số `name`. Nếu không có, hỏi tương tác (trừ khi --no-interaction).
     * - Bỏ hậu tố "settings" (không phân biệt hoa/thường).
     */
    protected function getNameArgument(): string
    {
        if ($this->cachedName !== null) {
            return $this->cachedName;
        }

        $name = $this->argument('name');

        if (! $name) {
            if ($this->option('no-interaction')) {
                $this->error('The name argument is required when using --no-interaction flag.');

                return self::FAILURE; // hoặc throw; nhưng giữ nguyên flow hiện tại nếu bạn muốn
            }

            $name = text(
                label: 'What is the name of the settings page?',
                placeholder: 'E.g. Site, General, Mail',
                hint: 'This will generate a {Name}Settings page class.',
                required: true
            );
        }

        // Remove trailing "settings" suffix (case-insensitive) and trim
        $name = (string) Str::of($name)->replaceMatches('/settings$/i', '')->trim();

        return $this->cachedName = $name;
    }

    /**
     * Lấy tham số `panel`. Nếu không có, gợi ý chọn từ danh sách panel khả dụng.
     * Trả về null nếu chỉ có 1 panel.
     */
    protected function getPanelArgument(): ?string
    {
        if ($this->panelCached) {
            return $this->cachedPanel;
        }

        $panel = $this->argument('panel');

        if (! $panel && ! $this->option('no-interaction')) {
            $availablePanels = $this->getAvailablePanels();

            if (\count($availablePanels) < 2) {
                $this->panelCached = true;

                return $this->cachedPanel = null;
            }

            $panel = suggest(
                label: 'Which Filament panel should this settings page be created for?',
                options: $availablePanels,
                placeholder: 'Leave empty for default panel',
                hint: 'This will determine the directory structure for your settings page.'
            ) ?: null;
        }

        $this->panelCached = true;

        return $this->cachedPanel = $panel;
    }

    /**
     * Lấy danh sách panel từ Filament (key là panel name).
     *
     * @return array<int, string>
     */
    protected function getAvailablePanels(): array
    {
        $panels = Filament::getPanels(); // ['admin' => PanelInstance, ...]
        return \array_keys($panels);
    }

    /**
     * Sinh view từ `stubs/view.stub` đến `resources/views/{viewName}.blade.php`.
     */
    public function createViewFromStub(string $viewName): void
    {
        $viewStubPath = __DIR__ . '/../../stubs/view.stub';
        $newViewPath  = \resource_path('views/' . \str_replace('.', '/', $viewName) . '.blade.php');

        if ($this->files->exists($newViewPath)) {
            $this->warn("File : {$newViewPath} already exists");
            return;
        }

        $viewStubContents = \file_get_contents($viewStubPath);

        if ($viewStubContents === false) {
            $this->warn("Unable to read view stub at {$viewStubPath}");
            return;
        }

        $viewContents = \str_replace('$VIEW_NAME$', $viewName, $viewStubContents);

        $this->makeDirectory(\dirname($newViewPath));
        $this->files->put($newViewPath, $viewContents);

        $this->info("View file : {$newViewPath} created");
    }

    /**
     * Đường dẫn file stub Page.
     */
    public function getStubPath(): string
    {
        return __DIR__ . '/../../stubs/page.stub';
    }

    /**
     * Biến thay thế trong stub.
     *
     * @return array{TITLE:string,PANEL:string,CLASS_NAME:string,SETTING_NAME:string}
     */
    public function getStubVariables(): array
    {
        $name  = $this->getNameArgument();
        $panel = $this->getPanelArgument();

        $singularClassName = $this->getSingularClassName($name);

        return [
            'TITLE'        => Str::headline($singularClassName),
            'PANEL'        => $panel ? \ucfirst($panel) . '\\' : '',
            'CLASS_NAME'   => $singularClassName,
            'SETTING_NAME' => Str::of($name)->headline()->lower()->slug(),
        ];
    }

    /**
     * Build nội dung file class từ stub + biến.
     *
     * @return string|false
     */
    public function getSourceFile(): string|false
    {
        return $this->getStubContents($this->getStubPath(), $this->getStubVariables());
    }

    /**
     * Thay thế các `$KEY$` trong stub bằng giá trị tương ứng.
     *
     * @param  array<string,string>  $stubVariables
     * @return string|false
     */
    public function getStubContents(string $stub, array $stubVariables = []): string|false
    {
        $contents = \file_get_contents($stub);

        if ($contents === false) {
            return false;
        }

        foreach ($stubVariables as $search => $replace) {
            $contents = \str_replace('$' . $search . '$', $replace, $contents);
        }

        return $contents;
    }

    /**
     * Đường dẫn file class đích.
     */
    public function getSourceFilePath(): string
    {
        $name  = $this->getNameArgument();
        $panel = $this->getPanelArgument();

        $panelPrefix = $panel ? \ucfirst($panel) . '\\' : '';

        $path = \base_path('app\\Filament\\' . $panelPrefix . 'Pages') . '\\' . $this->getSingularClassName($name) . 'Settings.php';

        return \str_replace('\\', '/', $path);
    }

    /**
     * Chuyển tên sang dạng singular, viết hoa từng từ,
     * ví dụ: "emails" -> "Email", "user settings" -> "User settings" (sau singular).
     */
    public function getSingularClassName(string $name): string
    {
        return \ucwords(Pluralizer::singular($name));
    }

    /**
     * Tạo thư mục đích nếu chưa tồn tại.
     */
    protected function makeDirectory(string $path): string
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0777, true, true);
        }

        return $path;
    }
}