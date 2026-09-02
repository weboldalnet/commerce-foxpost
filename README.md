# Foxpost szállítási provider a commerce-core-hoz

Ez a csomag a Foxpost csomagautomata/futár integrációját adja a `weboldalnet/commerce-core` alapú rendszerekhez.

> **Állapot:** csomagváz. A struktúra és az elnevezések készen állnak (composer autoload,
> service provider, publish/extend parancsok, config), a tényleges Foxpost API integráció
> (`ShippingProviderInterface` implementáció, automataválasztó, admin felület, útvonalak)
> még nincs megírva.

## Telepítés

A projekt `composer.json`-jában:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/weboldalnet/commerce-foxpost"
    }
]
```

```bash
composer require weboldalnet/commerce-foxpost:^1.0
```

A service provider Laravel package auto-discovery-vel regisztrálódik
(`Weboldalnet\CommerceFoxpost\CommerceFoxpostServiceProvider`).

## Konfiguráció

Publikálás a projektbe:

```bash
php artisan commerce-foxpost:install --tag=commerce-foxpost-all
php artisan commerce-foxpost:extend --view=all
```

Publikálható tagek:

| tag | tartalom |
| --- | --- |
| `commerce-foxpost-routes` | `routes/web.php` → `routes/commerce-foxpost.php` |
| `commerce-foxpost-settings` | `settings/` → `settings/commerce-foxpost` |
| `commerce-foxpost-config` | `config/commerce-foxpost.php` |
| `commerce-foxpost-all` | mindegyik |

`.env` beállítások:

```env
COMMERCE_FOXPOST_ENABLED=false
COMMERCE_FOXPOST_LOG_PAYLOADS=true
```

## Névterek és fájlszerkezet

```
src/CommerceFoxpostServiceProvider.php             – service provider (publish, route, view betöltés)
src/Console/InstallCommerceFoxpostCommand.php      – commerce-foxpost:install
src/Console/ExtendViewsCommerceFoxpostCommand.php  – commerce-foxpost:extend
src/Support/PackageHelper.php                      – publish lista és view kiegészítések
config/commerce-foxpost.php                        – konfiguráció
routes/web.php                                     – útvonalak (egyelőre üres váz)
settings/views/admin/                              – admin sidebar és package-functions blade
```
