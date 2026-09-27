<?php

declare(strict_types=1);

namespace App\Catalog;

use RuntimeException;

/**
 * No valid sign-in for the catalog source: none, expired, or rejected by eOil (HTTP 401).
 */
final class CatalogAuthenticationRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Sign-in to eOil is required.');
    }
}
