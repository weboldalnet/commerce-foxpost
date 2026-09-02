<?php

namespace Weboldalnet\CommerceFoxpost\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Weboldalnet\CommerceCore\Contracts\ShippingProviderInterface;
use Weboldalnet\CommerceCore\Data\ShipmentCreateResult;
use Weboldalnet\CommerceCore\Data\ShipmentRequestData;
use Weboldalnet\CommerceCore\Data\ShippingRateRequestData;
use Weboldalnet\CommerceCore\Data\ShippingRateResult;
use Weboldalnet\CommerceCore\Managers\ShippingManager;
use Weboldalnet\CommerceCore\Status\ShippingStatus;
use Weboldalnet\CommerceFoxpost\Services\FoxpostService;
use Weboldalnet\CommerceFoxpost\Services\FoxpostSettingsService;
use Weboldalnet\CommerceFoxpost\Support\FoxpostParcelBuilder;

/**
 * FoxPost szállítási provider.
 *
 * Két példányban regisztrálódik: csomagautomata (APM) és házhoz szállítás (HD).
 */
class FoxpostShippingProvider implements ShippingProviderInterface
{
    /** @var FoxpostService */
    protected $service;

    /** A szállítási mód kódja */
    protected $code;

    /** Házhoz szállítás-e ez a példány */
    protected $isHomeDelivery;

    public function __construct(FoxpostService $service = null, string $code = null, bool $isHomeDelivery = false)
    {
        $this->service = $service ?: app(FoxpostService::class);
        $this->code = $code ?: config('commerce-foxpost.provider_code', 'foxpost');
        $this->isHomeDelivery = $isHomeDelivery;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getName()
    {
        if ($this->isHomeDelivery) {
            return config('commerce-foxpost.home_delivery_label', 'Foxpost házhoz szállítás');
        }

        return config('commerce-foxpost.default_shipping_method_label', 'Foxpost csomagautomata');
    }

    /**
     * Csomagautomatás mód-e – a pénztár ez alapján dönti el, kell-e a választó.
     */
    public function isParcelShop(): bool
    {
        return !$this->isHomeDelivery;
    }

    /**
     * A szállítási mód jellege: ez alapján csoportosítja az admin és a pénztár.
     */
    public function getKind(): string
    {
        return $this->isHomeDelivery
            ? ShippingManager::KIND_HOME_DELIVERY
            : ShippingManager::KIND_PARCEL_SHOP;
    }

    /**
     * Szállítási díj.
     *
     * A FoxPost API nem ad díjkalkulációt – a díjszabás szerződésfüggő, ezért az
     * adminban megadott fix díjjal és ingyenes-határral számolunk.
     */
    public function calculate(ShippingRateRequestData $data)
    {
        $currency = (string) (FoxpostSettingsService::get('currency') ?: 'HUF');
        $rate = $this->resolveRate();

        $freeAbove = self::toAmount(FoxpostSettingsService::get('free_above'));
        $isFree = $freeAbove !== null && (float) $data->cartTotal >= $freeAbove;

        if ($rate === null) {
            Log::warning(
                'FoxPost: nincs beállítva szállítási díj, a rendelés 0 díjjal számol.',
                ['shipping_method' => $this->getCode()]
            );
        }

        $amount = ($isFree || $rate === null) ? 0.0 : $rate;

        return ShippingRateResult::success([
            'provider' => $this->getCode(),
            'shipping_method' => $this->getCode(),
            'rate' => $amount,
            'currency' => $currency,
            'is_free' => $isFree || $rate === null,
            'message' => $this->rateMessage($rate, $isFree, $amount, $currency),
        ]);
    }

    /**
     * A módhoz tartozó fix díj, vagy null, ha nincs megadva.
     * Házhoz szállításnál a saját díj az elsődleges; ha az nincs kitöltve, az
     * automatás díj érvényes – így elég egyetlen díjat megadni.
     */
    protected function resolveRate(): ?float
    {
        if ($this->isHomeDelivery) {
            $homeRate = self::toAmount(FoxpostSettingsService::get('home_delivery_rate'));

            if ($homeRate !== null) {
                return $homeRate;
            }
        }

        return self::toAmount(FoxpostSettingsService::get('rate'));
    }

    /**
     * Csomagfeladás: a FoxPost létrehozza a csomagot, majd letöltjük a címkét.
     */
    public function createShipment(ShipmentRequestData $data)
    {
        if (!FoxpostSettingsService::hasCredentials()) {
            return ShipmentCreateResult::failure([
                'status' => ShippingStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => 'Hiányzó FoxPost hozzáférési adatok. Töltsd ki a Webshop → FoxPost beállításokat.',
            ]);
        }

        $shipping = is_array($data->shippingData) ? $data->shippingData : [];

        // A kiválasztott átvevőpont azonosítója. A webshop egységesen a
        // "parcel_shop_id" kulcsot használja minden futárszolgálatra – így
        // ugyanaz a pénztár-mező szolgálja ki a GLS-t és a FoxPostot is.
        $apmId = $data->extra['apm_id']
            ?? ($shipping['parcel_shop_id'] ?? ($shipping['apm_id'] ?? null));

        // Automatás módnál az azonosító kötelező – enélkül a FoxPost nem tudja,
        // melyik automatába kell vinni a csomagot.
        if (!$this->isHomeDelivery && !$apmId) {
            return ShipmentCreateResult::failure([
                'status' => ShippingStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => 'Csomagautomatás szállításhoz hiányzik a kiválasztott automata azonosítója.',
            ]);
        }

        $parcel = FoxpostParcelBuilder::fromShipmentRequest($data, [
            'apm_id' => $apmId,
            'cod_amount' => $data->extra['cod_amount'] ?? 0,
            'is_home_delivery' => $this->isHomeDelivery,
        ]);

        $result = $this->service->createParcel($parcel, $data->orderId);

        if (!$result['success']) {
            return ShipmentCreateResult::failure([
                'status' => ShippingStatus::FAILED,
                'provider' => $this->getCode(),
                'message' => $result['message'] ?: 'A FoxPost csomagfeladás sikertelen.',
                'raw_response' => $result['raw'],
            ]);
        }

        $trackingNumber = $result['tracking_number'];
        $labelPath = $trackingNumber ? $this->storeLabel($trackingNumber, $data) : null;

        return ShipmentCreateResult::success([
            'status' => ShippingStatus::PREPARED,
            'provider' => $this->getCode(),
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingNumber ? FoxpostService::trackingUrl($trackingNumber) : null,
            'label_path' => $labelPath,
            'message' => $trackingNumber
                ? 'FoxPost csomag feladva, címke elkészült.'
                : 'A FoxPost elfogadta a feladást, de nem adott vissza azonosítót.',
            'raw_response' => $result['raw'],
            'extra' => ['parcel' => $result['parcel']],
        ]);
    }

    public function getTrackingUrl($trackingNumber)
    {
        return $trackingNumber ? FoxpostService::trackingUrl((string) $trackingNumber) : null;
    }

    /**
     * A címke PDF letöltése és letárolása a privát tárhelyre.
     * A fájl csak hitelesített admin route-on keresztül érhető el.
     */
    protected function storeLabel(string $trackingNumber, ShipmentRequestData $data): ?string
    {
        $label = $this->service->getLabels([$trackingNumber], null, $data->orderId);

        if (!$label['success'] || !$label['pdf']) {
            Log::warning('FoxPost címke letöltése sikertelen: ' . ($label['message'] ?? '-'), [
                'tracking_number' => $trackingNumber,
            ]);

            return null;
        }

        $basePath = trim((string) config('commerce-foxpost.storage.base_path', 'private/commerce-foxpost'), '/');
        $labelDir = trim((string) config('commerce-foxpost.storage.label_path', 'labels'), '/');

        // A teljes, 'local' diszkhez képesti útvonalat tároljuk, hogy a címke
        // letöltése provider-független lehessen az admin szállítmány-listában.
        $path = $basePath . '/' . $labelDir . '/foxpost-'
            . preg_replace('/[^A-Za-z0-9_-]/', '', $trackingNumber) . '.pdf';

        try {
            Storage::disk('local')->put($path, $label['pdf']);
        } catch (\Throwable $e) {
            Log::warning('FoxPost címke mentése sikertelen: ' . $e->getMessage());

            return null;
        }

        return $path;
    }

    /**
     * Beállítás-érték pénzösszeggé alakítása.
     * Üres/nem szám érték esetén null – vagyis "nincs megadva", nem 0 Ft.
     */
    protected static function toAmount($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function rateMessage(?float $rate, bool $isFree, float $amount, string $currency): string
    {
        if ($isFree) {
            return 'Ingyenes szállítás.';
        }

        if ($rate === null) {
            return 'Nincs beállítva FoxPost szállítási díj, ezért a rendszer 0 díjjal számol.';
        }

        return 'FoxPost szállítási díj: ' . rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',') . ' ' . $currency;
    }
}
