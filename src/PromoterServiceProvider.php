<?php

namespace Syndicate\Promoter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syndicate\Promoter\Commands\MakeSeoConfig;

class PromoterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('syndicate-promoter')
            ->hasViews()
            ->hasMigration('create_seo_data_table')
            ->hasCommand(MakeSeoConfig::class);
    }
}
