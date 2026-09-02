<?php

namespace Weboldalnet\CommerceFoxpost;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Managers\ShippingManager;
use Weboldalnet\CommerceCore\Services\ProviderLogger;
use Weboldalnet\CommerceFoxpost\Providers\FoxpostShippingProvider;
use Weboldalnet\CommerceFoxpost\Services\FoxpostService;
use Weboldalnet\CommerceFoxpost\Services\FoxpostSettingsService;
use Weboldalnet\CommerceFoxpost\Support\PackageHelper;
use Weboldalnet\CommerceFoxpost\Console\ExtendViewsCommerceFoxpostCommand;
use Weboldalnet\CommerceFoxpost\Console\InstallCommerceFoxpostCommand;

class CommerceFoxpostServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // route-ok és admin nézetek
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // migrációk (a csomag maga tölti be, ahogy a testvércsomagok is)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Provider regisztráció a commerce-core-ba.
        // Két szállítási módot kínálunk: csomagautomata és házhoz szállítás.
        try {
            $manager = $this->app->make(ShippingManager::class);

            $apmCode = config('commerce-foxpost.provider_code', 'foxpost');
            $homeCode = config('commerce-foxpost.home_delivery_code', 'foxpost_home');

            // Telepített integrációként mindig bejelentkezünk, hogy a webshop
            // beállítófelületén akkor is látszódjon (és onnan visszakapcsolható
            // legyen), ha a modul épp ki van kapcsolva.
            $manager->registerAvailable($apmCode, [
                'name' => config('commerce-foxpost.default_shipping_method_label', 'Foxpost csomagautomata'),
                'settings_url' => '/webshop/foxpost/settings',
                'settings_label' => 'FoxPost',
                'kind' => ShippingManager::KIND_PARCEL_SHOP,
            ]);
            $manager->registerAvailable($homeCode, [
                'name' => config('commerce-foxpost.home_delivery_label', 'Foxpost házhoz szállítás'),
                'settings_url' => '/webshop/foxpost/settings',
                'settings_label' => 'FoxPost',
                'kind' => ShippingManager::KIND_HOME_DELIVERY,
            ]);

            if (FoxpostSettingsService::getBool('enabled', false)) {
                $service = $this->app->make(FoxpostService::class);

                $manager->register($apmCode, new FoxpostShippingProvider($service, $apmCode, false));

                if (FoxpostSettingsService::getBool('home_delivery_enabled', true)) {
                    $manager->register($homeCode, new FoxpostShippingProvider($service, $homeCode, true));
                }
            }
        } catch (\Throwable $e) {
            Log::error('FoxPost regisztrációs hiba: ' . $e->getMessage());
        }

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

        $this->app->singleton(FoxpostSettingsService::class, function ($app) {
            return new FoxpostSettingsService();
        });

        $this->app->singleton(FoxpostService::class, function ($app) {
            $logger = null;
            try {
                $logger = $app->make(ProviderLogger::class);
            } catch (\Throwable $e) {
                // A naplózás hiánya ne akadályozza a csomagfeladást.
            }

            return new FoxpostService($logger);
        });

        $this->app->singleton(FoxpostShippingProvider::class, function ($app) {
            return new FoxpostShippingProvider($app->make(FoxpostService::class));
        });

        $this->commands([
            InstallCommerceFoxpostCommand::class,
            ExtendViewsCommerceFoxpostCommand::class,
        ]);
    }
}
