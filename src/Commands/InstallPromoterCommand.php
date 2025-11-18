<?php

namespace Syndicate\Promoter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallPromoterCommand extends Command
{
    protected $signature = 'install:promoter';

    public function handle(): int
    {
        Artisan::call('vendor:publish', ['--tag' => 'promoter-migrations']);

        return self::SUCCESS;
    }
}
