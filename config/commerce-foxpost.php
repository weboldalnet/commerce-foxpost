<?php
/**
 * Foxpost szállítási provider konfiguráció.
 *
 * Csomagváz: a tényleges Foxpost API beállítások (API kulcs, felhasználó,
 * környezet, automata lista) a szállítási funkció fejlesztésekor kerülnek ide.
 */
return [
    'enabled' => env('COMMERCE_FOXPOST_ENABLED', false),

    'provider_code' => 'foxpost',

    'default_shipping_method_label' => 'Foxpost csomagautomata',

    'log_payloads' => env('COMMERCE_FOXPOST_LOG_PAYLOADS', true),
];
