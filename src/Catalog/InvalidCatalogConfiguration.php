<?php

declare(strict_types=1);

namespace App\Catalog;

use RuntimeException;

/**
 * Catalog configuration is missing or unsafe. Messages never contain the API token.
 */
final class InvalidCatalogConfiguration extends RuntimeException {}
