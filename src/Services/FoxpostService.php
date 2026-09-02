<?php

namespace Weboldalnet\CommerceFoxpost\Services;

use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Services\ProviderLogger;

/**
 * FoxPost (FoxWeb) API kliens.
 *
 * A hitelesítés kétrétegű: HTTP Basic ÉS az api-key fejléc – egyik sem elhagyható.
 *
 * FIGYELEM – két buktató, amit élesben mértünk ki:
 *  1) A csomagfeladás HIBA esetén is HTTP 200-at ad, a hibák a válaszban vannak
 *     (valid=false, illetve parcels[].errors). A HTTP kódra hagyatkozni hibás
 *     sikerességet eredményezne.
 *  2) A címkeméretek közül az A5 elavult (OBSOLATE_LABEL_SIZE), pedig a Swagger
 *     még felsorolja. Használható: A6, A7, _85X85.
 */
class FoxpostService
{
    /** A FoxPost által ténylegesen elfogadott címkeméretek */
    const LABEL_SIZES = ['A6', 'A7', '_85X85'];

    /** Csomag méretkategóriák */
    const SIZES = ['xs', 's', 'm', 'l', 'xl'];

    /** @var ProviderLogger|null */
    protected $logger;

    public function __construct(ProviderLogger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * API hívás.
     *
     * @return array{success: bool, data: mixed, message: string|null, http_code: int}
     */
    public function call(string $method, string $path, $body = null, array $query = [], $orderId = null, bool $expectJson = true): array
    {
        if (!FoxpostSettingsService::hasCredentials()) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Hiányzó FoxPost hozzáférési adatok (felhasználónév, jelszó, api-key).',
                'http_code' => 0,
            ];
        }

        $url = rtrim(FoxpostSettingsService::apiBaseUrl(), '/') . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $this->log($path, is_array($body) ? $body : ['raw' => $body], null, true, null, $orderId);

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => FoxpostSettingsService::get('username') . ':' . FoxpostSettingsService::get('password'),
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'api-key: ' . FoxpostSettingsService::get('api_key'),
            ],
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            $this->log($path, $body, null, false, $curlError, $orderId);

            return ['success' => false, 'data' => null, 'message' => 'Hálózati hiba: ' . $curlError, 'http_code' => 0];
        }

        // Bináris válasz (címke PDF) – nem JSON
        if (!$expectJson) {
            if ($httpCode >= 200 && $httpCode < 300 && strpos($contentType, 'pdf') !== false) {
                $this->log($path, $body, ['pdf_bytes' => strlen($raw)], true, null, $orderId);

                return ['success' => true, 'data' => $raw, 'message' => null, 'http_code' => $httpCode];
            }

            $message = self::errorMessage(json_decode((string) $raw, true), $httpCode);
            $this->log($path, $body, ['raw' => mb_substr((string) $raw, 0, 300)], false, $message, $orderId);

            return ['success' => false, 'data' => null, 'message' => $message, 'http_code' => $httpCode];
        }

        $json = json_decode((string) $raw, true);

        if ($httpCode >= 400) {
            $message = self::errorMessage($json, $httpCode);
            $this->log($path, $body, $json, false, $message, $orderId);

            return ['success' => false, 'data' => $json, 'message' => $message, 'http_code' => $httpCode];
        }

        $this->log($path, $body, is_array($json) ? $json : null, true, null, $orderId);

        return ['success' => true, 'data' => $json, 'message' => null, 'http_code' => $httpCode];
    }

    /**
     * Csomag feladása.
     *
     * @return array{success: bool, parcel: array|null, tracking_number: string|null, message: string|null, raw: mixed}
     */
    public function createParcel(array $parcel, $orderId = null): array
    {
        $result = $this->call('POST', '/parcel', [$parcel], [
            // A sandboxban kötelezően false: ott nincs foxpost.hu fiók.
            'isWeb' => FoxpostSettingsService::isWeb() ? 'true' : 'false',
        ], $orderId);

        if (!$result['success']) {
            return [
                'success' => false,
                'parcel' => null,
                'tracking_number' => null,
                'message' => $result['message'],
                'raw' => $result['data'],
            ];
        }

        $data = (array) $result['data'];
        $created = $data['parcels'][0] ?? null;

        // FIGYELEM: a FoxPost hiba esetén is HTTP 200-at ad. A tényleges eredményt
        // a "valid" és a tételenkénti "errors" mutatja meg.
        $errors = self::collectErrors($data);

        if (empty($data['valid']) || $errors) {
            return [
                'success' => false,
                'parcel' => $created,
                'tracking_number' => null,
                'message' => $errors ?: 'A FoxPost elutasította a csomag feladását.',
                'raw' => $data,
            ];
        }

        return [
            'success' => true,
            'parcel' => $created,
            // A követési azonosító a clFoxId – a barcode feladáskor még üres.
            'tracking_number' => $created['clFoxId'] ?? null,
            'message' => null,
            'raw' => $data,
        ];
    }

    /**
     * Címke letöltése PDF-ként.
     *
     * @param array $trackingNumbers clFoxId azonosítók
     */
    public function getLabels(array $trackingNumbers, string $labelSize = null, $orderId = null): array
    {
        $size = strtoupper((string) ($labelSize ?: FoxpostSettingsService::get('label_size', 'A7')));

        if (!in_array($size, self::LABEL_SIZES, true)) {
            // Az A5-öt a FoxPost OBSOLATE_LABEL_SIZE hibával utasítja el.
            $size = 'A7';
        }

        $result = $this->call('POST', '/label/' . $size, array_values($trackingNumbers), [], $orderId, false);

        return [
            'success' => $result['success'],
            'pdf' => $result['success'] ? $result['data'] : null,
            'message' => $result['message'],
        ];
    }

    /**
     * Csomag állapotának lekérdezése.
     */
    public function getTracking(string $trackingNumber, $orderId = null): array
    {
        return $this->call('GET', '/tracking/' . rawurlencode($trackingNumber), null, [], $orderId);
    }

    /**
     * Csomag törlése (csak feladás előtt lehetséges).
     */
    public function deleteParcel(string $trackingNumber, $orderId = null): array
    {
        return $this->call('DELETE', '/parcel/' . rawurlencode($trackingNumber), null, [], $orderId);
    }

    /**
     * Kapcsolat és hozzáférés ellenőrzése.
     *
     * A címlista lekérdezése olvasó művelet – nem hoz létre csomagot.
     */
    public function testConnection(): array
    {
        $result = $this->call('GET', '/address');

        if ($result['success']) {
            return ['success' => true, 'message' => 'Sikeres kapcsolat a FoxPost rendszerével.'];
        }

        return ['success' => false, 'message' => $result['message'] ?: 'Sikertelen kapcsolat.'];
    }

    /**
     * A válaszban szereplő tételenkénti hibák összegyűjtése.
     */
    protected static function collectErrors(array $data): ?string
    {
        $messages = [];

        foreach ((array) ($data['parcels'] ?? []) as $parcel) {
            foreach ((array) ($parcel['errors'] ?? []) as $error) {
                $field = $error['field'] ?? '';
                $message = $error['message'] ?? '';
                $messages[] = trim($field . ': ' . self::translateError($message));
            }
        }

        return $messages ? implode('; ', $messages) : null;
    }

    /**
     * A FoxPost hibakódjainak magyar megfelelője.
     */
    protected static function translateError(string $code): string
    {
        $known = [
            'INVALID_APM_ID' => 'ismeretlen csomagautomata azonosító',
            'OBSOLATE_LABEL_SIZE' => 'elavult címkeméret (az A5 már nem használható)',
            'INVALID_EMAIL' => 'érvénytelen e-mail cím',
            'INVALID_PHONE' => 'érvénytelen telefonszám',
            'INVALID_SIZE' => 'érvénytelen csomagméret',
            'MISSING_FIELD' => 'hiányzó kötelező mező',
        ];

        return $known[$code] ?? $code;
    }

    protected static function errorMessage($json, int $httpCode): string
    {
        if (is_array($json) && !empty($json['error'])) {
            return 'FoxPost hiba: ' . self::translateError((string) $json['error']) . ' (HTTP ' . $httpCode . ')';
        }

        return 'FoxPost hiba (HTTP ' . $httpCode . ').';
    }

    /**
     * Naplózás a commerce_provider_logs táblába. Sosem buktatja el a hívást.
     * A jelszó és az api-key szándékosan nem része a naplózott payloadnak.
     */
    protected function log($endpoint, $request, $response, $isSuccess, $errorMessage = null, $orderId = null): void
    {
        if (!$this->logger || !FoxpostSettingsService::getBool('log_payloads', true)) {
            return;
        }

        try {
            $this->logger->logResponse(
                'shipping',
                config('commerce-foxpost.provider_code', 'foxpost'),
                $endpoint,
                is_array($request) ? $request : ['raw' => $request],
                is_array($response) ? $response : null,
                $isSuccess ? 200 : 400,
                (bool) $isSuccess,
                $errorMessage,
                is_numeric($orderId) ? (int) $orderId : null
            );
        } catch (\Throwable $e) {
            Log::warning('FoxPost provider log hiba: ' . $e->getMessage());
        }
    }

    /**
     * A csomag nyomon követésének nyilvános URL-je.
     */
    public static function trackingUrl(string $trackingNumber): string
    {
        return 'https://www.foxpost.hu/csomagkovetes?clfoxid=' . rawurlencode($trackingNumber);
    }
}
