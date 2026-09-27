<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Supplies the access token of the signed-in user for catalog API calls.
 */
interface AccessTokenProvider
{
    /**
     * @throws CatalogAuthenticationRequired when nobody is signed in or the sign-in has expired
     */
    public function accessToken(): string;
}
