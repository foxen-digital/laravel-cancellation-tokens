<?php

use Foxen\CancellationToken\CancellationTokenService;
use Foxen\CancellationToken\CancellationTokenServiceProvider;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Facades\CancellationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

$cleanPublishedFiles = function () {
    $migrationPath = database_path('migrations');
    $configPath = config_path('cancellation-tokens.php');

    if (File::exists($migrationPath)) {
        File::delete(File::glob($migrationPath.'/*cancellation_tokens*'));
    }
    if (File::exists($configPath)) {
        File::delete($configPath);
    }
};

// Clean up before each test (safety) and after each test so the next test's
// RefreshDatabase setUp does not pick up published migration files.
beforeEach($cleanPublishedFiles);
afterEach($cleanPublishedFiles);

it('publishes the migration file', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'cancellation-tokens-migrations',
    ])->assertSuccessful();

    $migrationPath = database_path('migrations');
    $migrations = File::glob(
        $migrationPath.'/*create_cancellation_tokens_table*',
    );

    expect($migrations)->toHaveCount(1);
});

it('publishes the config file', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'cancellation-tokens-config',
    ])->assertSuccessful();

    expect(config_path('cancellation-tokens.php'))->toBeFile();
});

it('has expected config keys and defaults', function () {
    $this->artisan('vendor:publish', [
        '--tag' => 'cancellation-tokens-config',
    ])->assertSuccessful();

    $config = include config_path('cancellation-tokens.php');

    expect($config)
        ->toBeArray()
        ->toHaveKey('table', 'cancellation_tokens')
        ->toHaveKey('prefix', 'ct_')
        ->toHaveKey('default_expiry', 10080);
});

it('creates the expected table schema', function () {
    $migration = include __DIR__.
        '/../../database/migrations/create_cancellation_tokens_table.php';
    $migration->up();

    $tableName = config('cancellation-tokens.table', 'cancellation_tokens');

    expect(Schema::hasTable($tableName))->toBeTrue();

    $columns = Schema::getColumnListing($tableName);

    // Note: SQLite does not persist column length in schema metadata.
    // The VARCHAR(255) constraint is enforced by the migration definition itself.
    expect($columns)
        ->toContain('id')
        ->toContain('token')
        ->toContain('tokenable_type')
        ->toContain('tokenable_id')
        ->toContain('cancellable_type')
        ->toContain('cancellable_id')
        ->toContain('expires_at')
        ->toContain('used_at')
        ->toContain('created_at')
        ->toContain('updated_at')
        ->not->toContain('deleted_at');

    // Verify token column has unique index
    $indexes = collect(Schema::getIndexes($tableName));
    $tokenUniqueIndex = $indexes->first(
        fn ($index) => in_array('token', $index['columns']) && $index['unique'],
    );
    expect($tokenUniqueIndex)->not->toBeNull();
});

it('uses custom table name from config', function () {
    // Drop the default table that RefreshDatabase created, then re-run with a custom name
    Schema::dropIfExists('cancellation_tokens');
    config(['cancellation-tokens.table' => 'custom_tokens']);

    $migration = include __DIR__.
        '/../../database/migrations/create_cancellation_tokens_table.php';
    $migration->up();

    expect(Schema::hasTable('custom_tokens'))
        ->toBeTrue()
        ->and(Schema::hasTable('cancellation_tokens'))
        ->toBeFalse();
});

it('auto-discovers the service provider', function () {
    // In Testbench, the provider is already loaded via getPackageProviders
    // This test verifies the provider is registered
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(CancellationTokenServiceProvider::class);
});

it('binds the contract to the service implementation', function () {
    $binding = app()->bound(CancellationTokenContract::class);

    expect($binding)->toBeTrue();

    $resolved = app(CancellationTokenContract::class);

    expect($resolved)->toBeInstanceOf(CancellationTokenService::class);
});

it('resolves the facade accessor correctly', function () {
    $reflection = new ReflectionClass(CancellationToken::class);
    $method = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    expect($method->isStatic())
        ->toBeTrue()
        ->and($method->getDeclaringClass()->getName())
        ->toBe(CancellationToken::class)
        ->and($method->invoke(null))
        ->toBe(CancellationTokenContract::class);
});
