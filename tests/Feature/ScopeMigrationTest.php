<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('upgrades the legacy group-key schema to scoped uniqueness', function (): void {
    Schema::drop('settings');
    Schema::create('settings', function (Blueprint $table): void {
        $table->id();
        $table->string('group');
        $table->string('key');
        $table->json('value')->nullable();
        $table->unique(['group', 'key']);
        $table->timestamps();
    });

    $migration = require dirname(__DIR__, 2).'/database/migrations/add_scope_to_settings_table.php.stub';
    $migration->up();

    expect(Schema::hasColumn('settings', 'scope'))->toBeTrue();
});
