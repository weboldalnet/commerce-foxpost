<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Foxpost szállítási modul útvonalai
|--------------------------------------------------------------------------
|
| Csomagváz: a Foxpost admin és automataválasztó útvonalak a szállítási
| funkció fejlesztésekor kerülnek ide, a testvércsomagok mintájára:
|
| Route::domain(getAdminDomain())->middleware(['web', 'admin_share', 'auth:admin'])
|     ->prefix('webshop/foxpost')->name('admin.webshop.foxpost.')->group(function () {
|         ...
|     });
|
*/
