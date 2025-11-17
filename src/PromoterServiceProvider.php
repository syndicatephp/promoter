<?php

namespace Syndicate\Promoter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syndicate\Promoter\Commands\PromoterCommand;

class PromoterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('promoter')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_promoter_table')
            ->hasCommand(PromoterCommand::class);
    }
}
