<?php

namespace Weboldalnet\CommerceFoxpost\Support;

use Weboldalnet\CommerceCore\Data\ShipmentRequestData;
use Weboldalnet\CommerceFoxpost\Services\FoxpostService;
use Weboldalnet\CommerceFoxpost\Services\FoxpostSettingsService;

/**
 * A commerce-core ShipmentRequestData átfordítása FoxPost "CreateParcelRequest"
 * payloaddá.
 *
 * A mezőnevek a FoxWeb API szerződését követik:
 *   recipientName, recipientPhone, recipientEmail (kötelező)
 *   destination                                   – csomagautomatához
 *   recipientCity, recipientZip, recipientAddress – házhoz szállításhoz
 *   size, cod, refCode, comment
 */
class FoxpostParcelBuilder
{
    /**
     * @param array $options apm_id, cod_amount, is_home_delivery, size, comment
     */
    public static function fromShipmentRequest(ShipmentRequestData $data, array $options = []): array
    {
        $shipping = is_array($data->shippingData) ? $data->shippingData : [];
        $isHomeDelivery = (bool) ($options['is_home_delivery'] ?? false);

        $parcel = [
            'recipientName' => (string) ($shipping['name'] ?? $data->customerName),
            'recipientPhone' => self::phone($data->customerPhone),
            'recipientEmail' => (string) $data->customerEmail,
            'size' => self::size($options['size'] ?? null),
            'refCode' => (string) ($data->orderNumber ?: $data->orderId),
        ];

        if ($isHomeDelivery) {
            $parcel['recipientCountry'] = strtoupper((string) ($shipping['country'] ?? 'HU'));
            $parcel['recipientZip'] = (string) ($shipping['zip'] ?? '');
            $parcel['recipientCity'] = (string) ($shipping['city'] ?? '');
            $parcel['recipientAddress'] = (string) ($shipping['address'] ?? '');
        } else {
            // Csomagautomata: a kiválasztott automata azonosítója (pl. hu404)
            $parcel['destination'] = (string) ($options['apm_id'] ?? ($shipping['apm_id'] ?? ''));
        }

        // Utánvét: a futár/automata szedi be az összeget
        $codAmount = (int) round((float) ($options['cod_amount'] ?? 0));
        if ($codAmount > 0 && FoxpostSettingsService::getBool('cod_enabled', true)) {
            $parcel['cod'] = $codAmount;
        }

        // Megjegyzés a csomaghoz. A ShipmentRequestData-nak nincs "note" mezője,
        // ezért az extra tömbből olvassuk.
        $comment = $options['comment'] ?? ($data->extra['note'] ?? null);
        if ($comment) {
            $parcel['comment'] = mb_substr((string) $comment, 0, 200);
        }

        return array_filter($parcel, function ($value) {
            return $value !== '' && $value !== null;
        });
    }

    /**
     * Csomagméret. A FoxPost méretkategóriát kér (xs/s/m/l/xl), nem súlyt –
     * ezért az adminban megadott alapértelmezés érvényes.
     */
    protected static function size(?string $size): string
    {
        $size = strtolower((string) ($size ?: FoxpostSettingsService::get('size', 'm')));

        return in_array($size, FoxpostService::SIZES, true) ? $size : 'm';
    }

    /**
     * A FoxPost a telefonszámot szigorúbban ellenőrzi, mint a webshop űrlapja:
     * a szóközök és kötőjelek hibát okoznának.
     */
    protected static function phone(?string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $phone);

        return (string) $phone;
    }
}
