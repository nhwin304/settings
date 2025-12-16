# Settings – Lightweight settings & content manager for Filament

[![Run Tests](https://github.com/nhwin304/settings/actions/workflows/tests.yaml/badge.svg)](https://github.com/nhwin304/settings/actions/workflows/tests.yaml)
[![Code Style](https://github.com/nhwin304/settings/actions/workflows/code-style.yml/badge.svg)](https://github.com/nhwin304/settings/actions/workflows/code-style.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](https://opensource.org/licenses/MIT)

- [Settings – Lightweight settings \& content manager for Filament](#db-config--lightweight-settings--content-manager-for-filament)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [🚀 Getting Started](#-getting-started)

## Requirements

- PHP version supported by your Laravel installation
- Laravel 12
- A database engine with JSON support (MySQL 5.7+, MariaDB 10.2.7+, PostgreSQL, SQLite recent versions)
- Filament 4
- PHP ^8.2 


## Installation

1. **Install the package** via Composer:

    ```bash
    composer require nhwin/settings
    ```

2. **Publish the assets** (Configuration and Migration):

    ```bash

    <!-- Publish Configuration -->
    php artisan vendor:publish --tag=settings-config
    
	<!-- Publish Migrations -->
    php artisan vendor:publish --tag=settings-migrations

	<!-- Publish Lang -->
    php artisan vendor:publish --tag=settings-translations

    <!-- or -->

    php artisan vendor:publish --provider="Nhwin\Settings\Providers\SettingServiceProvider"
  
    ```

3. **Run the migration**:

    ```bash
    php artisan migrate
    ```

    This command executes the migration file that you just published, creating the `settings` table (or the custom table name you defined in the config file) in your database. Your package is now ready to use!

> [!TIP]
> If you want to use a custom table name instead of `settings`, edit the configuration file `config/settings.php` before running the migration. See the [Configuration](#configuration) section for details.

## 🚀 Getting Started

Get up and running in just a few steps:

1. **Generate Your First Settings Page**

    ```bash
    php artisan make:settings Website
    ```

    This command creates a new Filament page (e.g., App/Filament/Pages/WebsiteSettings.php). You can repeat this step for more pages as needed.

> [!NOTE]
> The generator will automatically add the “Settings” suffix to the page name for consistency (e.g., WebsiteSettings), but you can use any group name you wish.

2. **Define Your Fields**

    Open the generated page and customize the form() method with your desired fields:

    ```php
    public function form(Form $form): Form
    {
        return $form
            ->components([
                TextInput::make('site_name')->label('Site Name'),
                TextInput::make('contact_email')->label('Contact Email'),
                Toggle::make('maintenance_mode')->label('Maintenance Mode'),
            ])
            ->statePath('data');
    }
    ```

> [!NOTE]
> You may use **any Filament form fields or layout components - including third-party ones -** to build your settings and content pages, giving you full flexibility in how data is structured and edited.

3. **Save and Edit Settings from the Admin Panel**

    You can now edit these settings directly in your Filament admin panel—no extra boilerplate needed.

4. **Use Your Settings Anywhere**

    Retrieve your configuration values easily:
   - In PHP:

       ```php
       $siteName = settings('website.site_name', 'Default Site Name');
       ```

   - In Blade:

       ```html
       <h1>{{ settings('website.site_name', 'Default Site Name') }}</h1>

       <!-- or -->

       <h1>@settings('website.site_name', 'Default Site Name')</h1>
       ```

**That’s it!** 🎉

Define your fields, save from the admin panel, and access your settings anywhere in your Laravel app.