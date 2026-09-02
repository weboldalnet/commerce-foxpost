<?php

namespace Weboldalnet\CommerceFoxpost\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Weboldalnet\CommerceFoxpost\Models\FoxpostSetting;

/**
 * Foxpost beállítások: az adatbázisban tárolt (adminból szerkesztett) érték az
 * elsődleges, hiányában a config/.env alapértelmezés érvényes.
 */
class FoxpostSettingsService
{
    protected static $cacheKey = 'commerce_foxpost_settings';
    protected static $typeCacheKey = 'commerce_foxpost_setting_types';

    /**
     * A lapos beállítás-kulcsok leképezése a beágyazott config útvonalakra.
     */
    protected const CONFIG_PATH_MAP = [
        'username' => 'authentication.username',
        'password' => 'authentication.password',
        'api_key' => 'authentication.api_key',
        'size' => 'defaults.size',
        'label_size' => 'defaults.label_size',
        'cod_enabled' => 'defaults.cod_enabled',
        'currency' => 'defaults.currency',
        'rate' => 'defaults.rate',
        'home_delivery_rate' => 'defaults.home_delivery_rate',
        'free_above' => 'defaults.free_above',
    ];

    public static function viewKeys(): array
    {
        return [
            'enabled', 'environment',
            'username', 'password', 'api_key',
            'size', 'label_size', 'cod_enabled',
            'currency', 'rate', 'home_delivery_rate', 'free_above',
            'home_delivery_enabled',
        ];
    }

    /** Titkosítva tárolandó kulcsok */
    public static function encryptedKeys(): array
    {
        return ['password', 'api_key'];
    }

    /** Logikai (checkbox) kulcsok */
    public static function booleanKeys(): array
    {
        return ['enabled', 'cod_enabled', 'home_delivery_enabled'];
    }

    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::$cacheKey, function () {
                return FoxpostSetting::all()->pluck('value', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function types(): array
    {
        try {
            return Cache::rememberForever(self::$typeCacheKey, function () {
                return FoxpostSetting::all()->pluck('type', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function configDefault($key, $default = null)
    {
        // Előbb lapos kulcs (pl. enabled, environment), utána a leképezett útvonal.
        $flat = config('commerce-foxpost.' . $key);
        if ($flat !== null && $flat !== '') {
            return $flat;
        }

        if (isset(self::CONFIG_PATH_MAP[$key])) {
            $mapped = config('commerce-foxpost.' . self::CONFIG_PATH_MAP[$key]);
            if ($mapped !== null && $mapped !== '') {
                return $mapped;
            }
        }

        return $default;
    }

    public static function get($key, $default = null)
    {
        $settings = self::all();
        $hasDbValue = array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '';
        $value = $hasDbValue ? $settings[$key] : self::configDefault($key, $default);

        $type = self::types()[$key] ?? null;

        // A titkosítás csak a DB-ben tárolt értékre vonatkozik, a config/.env értéke nyers.
        if ($hasDbValue && $type === 'encrypted' && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    public static function getBool($key, $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function save($key, $value, $type = 'string', $group = 'general'): void
    {
        if ($type === 'encrypted' && $value) {
            $value = Crypt::encryptString($value);
        }

        FoxpostSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );

        self::clearCache();
    }

    /**
     * Van-e elegendő adat a FoxPost hívásokhoz?
     * Mindhárom kell: a Basic hitelesítés és az api-key együtt működik.
     */
    public static function hasCredentials(): bool
    {
        foreach (['username', 'password', 'api_key'] as $key) {
            if ((string) self::get($key) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * 'test' vagy 'prod' – minden más értéket tesztnek tekintünk.
     */
    public static function environment(): string
    {
        return self::get('environment', 'test') === 'prod' ? 'prod' : 'test';
    }

    public static function isProd(): bool
    {
        return self::environment() === 'prod';
    }

    public static function apiBaseUrl(): string
    {
        return (string) config('commerce-foxpost.endpoints.' . self::environment(), '');
    }

    public static function apmFeedUrl(): string
    {
        return (string) config('commerce-foxpost.apm_feed.' . self::environment(), '');
    }

    /**
     * A csomag megjelenjen-e a foxpost.hu felületén.
     * Teszt környezetben kötelezően false: ott nincs foxpost.hu fiók.
     */
    public static function isWeb(): bool
    {
        if (!self::isProd()) {
            return false;
        }

        return (bool) config('commerce-foxpost.is_web', false);
    }

    public static function clearCache(): void
    {
        Cache::forget(self::$cacheKey);
        Cache::forget(self::$typeCacheKey);
    }
}
