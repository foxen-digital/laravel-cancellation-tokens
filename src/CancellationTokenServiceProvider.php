<?php

namespace Foxen\CancellationToken;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Foxen\CancellationToken\Commands\CancellationTokenCommand;

class CancellationTokenServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-cancellation-tokens')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_cancellation_tokens_table')
    }
}
