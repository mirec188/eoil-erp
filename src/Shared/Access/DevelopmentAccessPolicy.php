<?php

declare(strict_types=1);

namespace App\Shared\Access;

use App\Catalog\CatalogSource;
use App\Environment;

/**
 * Decides whether this build may serve requests in the given configuration.
 *
 * The first slice is a local development base with synthetic data and no user identity or
 * authorization. Production is therefore refused unconditionally, whatever the catalog source.
 * M2 must replace this with a real eOil identity/authorization mechanism; there is no switch,
 * setting or string that enables production here.
 */
final readonly class DevelopmentAccessPolicy
{
    public function decide(string $appEnv, CatalogSource $catalogSource): AccessDecision
    {
        if ($appEnv === Environment::PROD) {
            return AccessDecision::deny(
                $catalogSource === CatalogSource::Fixture
                    ? 'Ukážkové údaje nie sú povolené v produkčnej konfigurácii. Táto verzia nemá prihlásenie.'
                    : 'Táto verzia nemá prihlásenie používateľov eOil, preto nesmie bežať v produkcii.',
            );
        }

        return AccessDecision::allow(
            $catalogSource === CatalogSource::Fixture
                ? 'Lokálny vývoj s ukážkovými údajmi.'
                : 'Lokálny vývoj s HTTP adaptérom katalógu.',
        );
    }
}
