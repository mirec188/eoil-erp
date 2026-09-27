<?php

declare(strict_types=1);

namespace App\Catalog;

use RuntimeException;

/**
 * eOil refused the signed-in user (HTTP 403): blocked account or no ERP role.
 */
final class CatalogAccessDenied extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('eOil refused access for this user.');
    }
}
