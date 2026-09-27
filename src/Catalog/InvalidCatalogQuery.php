<?php

declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;

/**
 * Invalid input to the catalog port (search term, page, page size or ID). Web actions map it to HTTP 400.
 */
final class InvalidCatalogQuery extends InvalidArgumentException {}
