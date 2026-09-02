<?php

namespace Weboldalnet\CommerceFoxpost;

use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceFoxpost\Support\PackageHelper;
use Weboldalnet\CommerceFoxpost\Console\ExtendViewsCommerceFoxpostCommand;
use Weboldalnet\CommerceFoxpost\Console\InstallCommerceFoxpostCommand;

class CommerceFoxpostServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // route-ok
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // migrációk
        //$this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $publishList = [];
        foreach (PackageHelper::PACKAGE_LIST as $name => $publish) {
            $this->publishes([
                $publish['source'] => base_path($publish['destination']),
            ], PackageHelper::PACKAGE_PREFIX . '-' . $name);

            $publishList[$publish['source']] = base_path($publish['destination']);
        }

        $this->publishes($publishList, PackageHelper::PACKAGE_PREFIX . '-all');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/commerce-foxpost.php', 'commerce-foxpost');

        $this->commands([
            InstallCommerceFoxpostCommand::class,
        ]);

        $this->commands([
            ExtendViewsCommerceFoxpostCommand::class,
        ]);
    }
}
