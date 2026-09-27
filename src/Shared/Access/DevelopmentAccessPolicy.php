<?php

declare(strict_types=1);

namespace App\Shared\Access;

use App\Catalog\CatalogSource;
use App\Environment;

/**
 * Decides whether this build may serve requests in the given configuration.
 *
 * The ERP is a local development base. The demo catalog has no sign-in; the eOil sign-in of M2
 * (CATALOG_SOURCE=http) is verified only locally — not deployed over HTTPS and not security-reviewed.
 * Production is therefore refused unconditionally, whatever the catalog source. There is no switch,
 * setting or string that enables production here; opening it is a separate, reviewed change.
 */
final readonly class DevelopmentAccessPolicy
{
    public function decide(string $appEnv, CatalogSource $catalogSource): AccessDecision
    {
        if ($appEnv === Environment::PROD) {
            return AccessDecision::deny(
                $catalogSource === CatalogSource::Fixture
                    ? 'Ukážkové údaje nie sú povolené v produkčnej konfigurácii. Ukážkový režim nemá prihlásenie.'
                    : 'Prihlásenie cez eOil je overené iba lokálne; produkčné nasadenie ERP ešte nie je schválené.',
            );
        }

        return AccessDecision::allow(
            $catalogSource === CatalogSource::Fixture
                ? 'Lokálny vývoj s ukážkovými údajmi.'
                : 'Lokálny vývoj s HTTP adaptérom katalógu.',
        );
    }
}
