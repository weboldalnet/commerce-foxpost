<?php
/**
 * Foxpost szállítási provider konfiguráció.
 *
 * FONTOS: az itt szereplő értékek csak ALAPÉRTELMEZÉSEK. Az admin felületen
 * (Webshop → FoxPost) megadott – és titkosítva tárolt – beállítások mindig
 * erősebbek, ugyanaz a minta, mint a commerce-gls és commerce-barion csomagoknál.
 */
return [
    'enabled' => env('COMMERCE_FOXPOST_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Szállítási módok
    |--------------------------------------------------------------------------
    |
    | A FoxPost kétféle kézbesítést kínál: csomagautomata (APM) és házhoz
    | szállítás (HD). Mindkettő külön szállítási módként jelenik meg a pénztárban.
    |
    */
    'provider_code' => 'foxpost',
    'default_shipping_method_label' => 'Foxpost csomagautomata',

    'home_delivery_code' => 'foxpost_home',
    'home_delivery_label' => 'Foxpost házhoz szállítás',

    /*
    |--------------------------------------------------------------------------
    | Környezet
    |--------------------------------------------------------------------------
    |
    | 'test' vagy 'prod'. Minden más értéket tesztnek tekintünk, hogy egy
    | elgépelés soha ne adjon fel véletlenül valódi csomagot.
    |
    */
    'environment' => env('COMMERCE_FOXPOST_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | API hitelesítés
    |--------------------------------------------------------------------------
    |
    | A FoxPost egyszerre kéri a HTTP Basic hitelesítést (felhasználónév/jelszó)
    | ÉS az api-key fejlécet – a kettő közül egyik sem elhagyható.
    |
    */
    'authentication' => [
        'username' => env('COMMERCE_FOXPOST_USERNAME', ''),
        'password' => env('COMMERCE_FOXPOST_PASSWORD', ''),
        'api_key' => env('COMMERCE_FOXPOST_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Végpontok
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'test' => 'https://webapi-test.foxpost.hu/api',
        'prod' => 'https://webapi.foxpost.hu/api',
    ],

    /*
    | Az automaták listája. A teszt környezetben a hu1000-nél kisebb azonosítójú
    | automatákat érdemes használni – a többi még nem került át, és INVALID_APM_ID
    | hibát adhat.
    */
    'apm_feed' => [
        'test' => 'https://cdn.foxpost.hu/sandbox_foxplus.json',
        'prod' => 'https://cdn.foxpost.hu/foxplus.json',
    ],
    // Az automata-lista gyorsítótárazása percben
    'apm_cache_minutes' => env('COMMERCE_FOXPOST_APM_CACHE_MINUTES', 720),

    /*
    |--------------------------------------------------------------------------
    | Alapértelmezett csomag beállítások
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        /*
        | Csomagméret: xs, s, m, l, xl. A FoxPost méretkategóriát kér, nem súlyt.
        | Egyelőre minden csomag ezt a méretet kapja.
        */
        'size' => env('COMMERCE_FOXPOST_SIZE', 'm'),

        /*
        | Címkeméret. FIGYELEM: az A5 elavult, a FoxPost OBSOLATE_LABEL_SIZE
        | hibával elutasítja – élesben ellenőrizve. Használható: A6, A7, _85X85.
        */
        'label_size' => env('COMMERCE_FOXPOST_LABEL_SIZE', 'A7'),

        // Utánvét engedélyezése FoxPoston keresztül
        'cod_enabled' => true,

        'currency' => 'HUF',
        // Szállítási díjak: a FoxPost API nem ad díjkalkulációt, a díjszabás szerződésfüggő
        'rate' => env('COMMERCE_FOXPOST_RATE', null),
        'home_delivery_rate' => env('COMMERCE_FOXPOST_HOME_RATE', null),
        'free_above' => env('COMMERCE_FOXPOST_FREE_ABOVE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tárolási útvonalak (Laravel Storage-on belül)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'base_path' => 'private/commerce-foxpost',
        'label_path' => 'labels',
    ],

    /*
    | A csomag megjelenjen-e a foxpost.hu webes felületén. A sandboxban KÖTELEZŐEN
    | false, mert ott nincs foxpost.hu fiók a teszt felhasználóhoz.
    */
    'is_web' => env('COMMERCE_FOXPOST_IS_WEB', false),

    'log_payloads' => env('COMMERCE_FOXPOST_LOG_PAYLOADS', true),
];
