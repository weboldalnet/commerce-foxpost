<?php

namespace Weboldalnet\CommerceFoxpost\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Weboldalnet\CommerceFoxpost\Support\PackageHelper;

class InstallCommerceFoxpostCommand extends Command
{
    protected $signature = PackageHelper::PACKAGE_PREFIX . ':install {--tag=}';
    protected $description = PackageHelper::PACKAGE_NAME . ' fájlok publikálása a projectbe';

    public function handle()
    {
        $tag = $this->option('tag');

        if (empty($tag)) {
            $tag = PackageHelper::PACKAGE_PREFIX . '-all';
        }

        $this->info(PackageHelper::PACKAGE_NAME . ' fájlok publikálása a projectbe...');

        Artisan::call('vendor:publish', [
            '--provider' => 'Weboldalnet\\CommerceFoxpost\\CommerceFoxpostServiceProvider',
            '--tag' => $tag,
            '--force' => true,
        ]);

        $this->info(PackageHelper::PACKAGE_NAME . ' telepítése sikeres!');
    }
}
