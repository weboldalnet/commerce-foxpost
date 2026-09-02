<?php

namespace Weboldalnet\CommerceFoxpost\Support;

class PackageHelper
{
    const PACKAGE_NAME = 'Foxpost szállítási modul';
    const PACKAGE_PREFIX = 'commerce-foxpost';

    const PACKAGE_LIST = [
        'routes' => [
            'name' => 'routes | routes/web.php',
            'source' => __DIR__.'/../../routes/web.php',
            'destination' => '/routes/commerce-foxpost.php',
        ],
        'settings' => [
            'name' => 'settings | settings/',
            'source' => __DIR__.'/../../settings',
            'destination' => '/settings/commerce-foxpost',
        ],
        'config' => [
            'name' => 'config | config/commerce-foxpost.php',
            'source' => __DIR__.'/../../config/commerce-foxpost.php',
            'destination' => '/config/commerce-foxpost.php',
        ],
    ];

    const PACKAGE_VIEW_EXTENDS = [
        'sidebar' => [
            'view_path' => '/resources/views/admin/package-container/admin-p-sidebar.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.sidebar')"
        ],
        'package-settings' => [
            'view_path' => '/resources/views/admin/package-settings/package-settings-container.blade.php',
            'include' => "@include('" . self::PACKAGE_PREFIX . "::admin.package-functions')"
        ],
    ];
}
