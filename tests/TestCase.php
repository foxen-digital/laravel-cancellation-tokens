<?php

namespace Foxen\CancellationToken\Tests;

use Foxen\CancellationToken\CancellationTokenServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (
                string $modelName,
            ) => 'Foxen\\CancellationToken\\Database\\Factories\\'.
                class_basename($modelName).
                'Factory',
        );
    }

    protected function getPackageProviders($app)
    {
        return [CancellationTokenServiceProvider::class];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('app.key', base64_encode(random_bytes(64)));
    }
}
