<?php

namespace Weboldalnet\CommerceFoxpost\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FoxPost csomagautomaták listája.
 *
 * A FoxPost nem ad beágyazható választó-komponenst, viszont közzéteszi az
 * automaták adatait JSON-ban. Ezt töltjük le, normalizáljuk és gyorsítótárazzuk –
 * a pénztár választója ebből dolgozik.
 */
class FoxpostApmService
{
    /**
     * Az automaták normalizált listája.
     *
     * @return array<int, array{id: string, name: string, zip: string, city: string, address: string, lat: float, lng: float, open: array}>
     */
    public static function all(): array
    {
        $minutes = (int) config('commerce-foxpost.apm_cache_minutes', 720) ?: 720;
        $cacheKey = 'commerce_foxpost_apms_' . FoxpostSettingsService::environment();

        return Cache::remember($cacheKey, now()->addMinutes($minutes), function () {
            return self::fetch();
        });
    }

    /**
     * Keresés irányítószám, város vagy név szerint.
     */
    public static function search(?string $term, int $limit = 50): array
    {
        $all = self::all();
        $term = trim((string) $term);

        if ($term === '') {
            return array_slice($all, 0, $limit);
        }

        $needle = self::normalize($term);

        $matches = array_values(array_filter($all, function ($apm) use ($needle) {
            foreach (['zip', 'city', 'name', 'address'] as $field) {
                if (strpos(self::normalize((string) $apm[$field]), $needle) !== false) {
                    return true;
                }
            }

            return false;
        }));

        return array_slice($matches, 0, $limit);
    }

    /**
     * Egy automata adatai azonosító alapján.
     */
    public static function find(?string $id): ?array
    {
        if (!$id) {
            return null;
        }

        foreach (self::all() as $apm) {
            if (strcasecmp($apm['id'], $id) === 0) {
                return $apm;
            }
        }

        return null;
    }

    /**
     * A lista letöltése és normalizálása.
     */
    protected static function fetch(): array
    {
        $url = FoxpostSettingsService::apmFeedUrl();

        if (!$url) {
            return [];
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false || $httpCode !== 200) {
                Log::warning('FoxPost automata-lista letöltése sikertelen.', ['url' => $url, 'http' => $httpCode]);

                return [];
            }

            $data = json_decode($raw, true);
            $items = $data['items'] ?? (is_array($data) ? $data : []);
        } catch (\Throwable $e) {
            Log::warning('FoxPost automata-lista hiba: ' . $e->getMessage());

            return [];
        }

        $isTest = !FoxpostSettingsService::isProd();
        $result = [];

        foreach ((array) $items as $item) {
            $id = (string) ($item['operator_id'] ?? '');

            if ($id === '') {
                continue;
            }

            // Csak azok az automaták, ahova csomagot lehet kézbesíteni.
            $features = (array) ($item['features'] ?? []);
            if ($features && !in_array('delivery', $features, true)) {
                continue;
            }

            // A teszt környezetben a FoxPost kérése szerint csak a hu1000 alatti
            // automaták használhatók – a többi INVALID_APM_ID hibát adhat.
            if ($isTest && !self::isTestUsable($id)) {
                continue;
            }

            $contact = $item['contact'] ?? [];

            $result[] = [
                'id' => $id,
                'name' => (string) ($item['name'] ?? $id),
                'zip' => (string) ($item['zip'] ?? ($contact['postalCode'] ?? '')),
                'city' => (string) ($item['city'] ?? ($contact['city'] ?? '')),
                'address' => (string) ($item['street'] ?? ($item['address'] ?? '')),
                'lat' => (float) ($item['geolat'] ?? 0),
                'lng' => (float) ($item['geolng'] ?? 0),
                'open' => (array) ($item['open'] ?? []),
            ];
        }

        usort($result, function ($a, $b) {
            return [$a['zip'], $a['city']] <=> [$b['zip'], $b['city']];
        });

        return $result;
    }

    /**
     * Teszt környezetben csak a hu1000 alatti azonosítójú automaták használhatók.
     */
    protected static function isTestUsable(string $id): bool
    {
        if (!preg_match('/^hu(\d+)$/i', $id, $m)) {
            return false;
        }

        return (int) $m[1] < 1000;
    }

    /**
     * Ékezet- és kisbetű-független összehasonlításhoz.
     */
    protected static function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');

        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        ]);
    }

    public static function clearCache(): void
    {
        foreach (['test', 'prod'] as $environment) {
            Cache::forget('commerce_foxpost_apms_' . $environment);
        }
    }
}
