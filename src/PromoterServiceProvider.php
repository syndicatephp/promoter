<?php

namespace Syndicate\Promoter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syndicate\Promoter\Commands\MakeSeo;

class PromoterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('promoter')
            ->hasViews()
            ->hasMigration('create_seo_data_table')
            ->hasCommand(MakeSeo::class);
    }
}
