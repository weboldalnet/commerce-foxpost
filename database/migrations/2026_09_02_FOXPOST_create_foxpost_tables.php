<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foxpost beállítások tábla.
 *
 * Az API hozzáférés (felhasználónév, jelszó, api-key), a feladási beállítások és
 * a szállítási díjak az admin felületről is megadhatók legyenek, ne csak .env-ből.
 * A jelszó és az api-key titkosítva tárolódik.
 *
 * Ugyanaz a séma, mint a commerce-gls és commerce-simplepay csomagoknál.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public.commerce_foxpost_settings')) {
            Schema::create('public.commerce_foxpost_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                // string, boolean, integer, json, encrypted
                $table->string('type')->default('string');
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public.commerce_foxpost_settings');
    }
};
