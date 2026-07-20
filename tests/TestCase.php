<?php

namespace Nhwin\Settings\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nhwin\Settings\Providers\SettingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->default('global');
            $table->string('group');
            $table->string('key');
            $table->json('value')->nullable();
            $table->unique(['scope', 'group', 'key']);
            $table->timestamps();
        });
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SettingServiceProvider::class];
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:bmjN9j7vX8tBhjczHDWz3wkR7RZP8x4LL8CM3l2lQn8=');
        $app['config']->set('app.cipher', 'AES-256-CBC');
    }
}
