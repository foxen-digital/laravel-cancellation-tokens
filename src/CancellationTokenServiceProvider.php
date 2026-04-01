<?php

namespace Foxen\CancellationToken;

use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CancellationTokenServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cancellation-tokens')
            ->hasConfigFile()
            ->hasMigration('create_cancellation_tokens_table')
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        $this->app->bind(
            CancellationTokenContract::class,
            CancellationTokenService::class
        );
    }
}
