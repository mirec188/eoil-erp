<?php

declare(strict_types=1);

namespace App\Catalog;

enum CatalogSource: string
{
    /** Synthetic demo data bundled with the application. Never real eOil data. */
    case Fixture = 'fixture';

    /** HTTP adapter for the proposed eOil ERP API (contract draft for M2). */
    case Http = 'http';
}
