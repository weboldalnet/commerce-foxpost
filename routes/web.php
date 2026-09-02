<?php

use Illuminate\Support\Facades\Route;
use Weboldalnet\CommerceFoxpost\Http\Controllers\FoxpostApmController;
use Weboldalnet\CommerceFoxpost\Http\Controllers\Admin\FoxpostSettingController;

/*
|--------------------------------------------------------------------------
| Foxpost szállítási modul útvonalai
|--------------------------------------------------------------------------
*/

// A pénztár automata-választója ezt hívja (nyilvános, csak olvasás).
Route::domain(getSiteDomain())
    ->middleware(['web'])
    ->group(function () {
        Route::get('/commerce/foxpost/apms', [FoxpostApmController::class, 'index'])
            ->name('commerce.foxpost.apms');
    });

// FIGYELEM: a platformon 'admin_share' a middleware alias, nem 'admin'.
Route::domain(getAdminDomain())
    ->middleware(['web', 'admin_share', 'auth:admin'])
    ->prefix('webshop/foxpost')
    ->name('admin.webshop.foxpost.')
    ->group(function () {
        Route::get('/settings', [FoxpostSettingController::class, 'index'])->name('settings');
        Route::post('/settings', [FoxpostSettingController::class, 'update'])->name('settings.update');
        Route::post('/test-connection', [FoxpostSettingController::class, 'testConnection'])->name('test-connection');
        Route::post('/refresh-apms', [FoxpostSettingController::class, 'refreshApms'])->name('refresh-apms');
    });
