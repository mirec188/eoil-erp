<?php

declare(strict_types=1);

namespace App\Catalog;

use function mb_check_encoding;
use function mb_strlen;
use function trim;

final readonly class CatalogQuery
{
    public const PAGE_SIZE = 25;
    public const MAX_TERM_LENGTH = 200;
    public const MAX_PAGE = 10000;

    /** Search term, trimmed. Matches product name or an exact MRP number. */
    public string $term;
    public int $page;
    public int $pageSize;

    public function __construct(string $term = '', int $page = 1, int $pageSize = self::PAGE_SIZE)
    {
        if (!mb_check_encoding($term, 'UTF-8')) {
            throw new InvalidCatalogQuery('Search term must be valid UTF-8.');
        }
        $term = trim($term);
        if (mb_strlen($term) > self::MAX_TERM_LENGTH) {
            throw new InvalidCatalogQuery('Search term must have at most ' . self::MAX_TERM_LENGTH . ' characters.');
        }
        if ($page < 1 || $page > self::MAX_PAGE) {
            throw new InvalidCatalogQuery('Page must be between 1 and ' . self::MAX_PAGE . '.');
        }
        if ($pageSize !== self::PAGE_SIZE) {
            throw new InvalidCatalogQuery('Page size is fixed to ' . self::PAGE_SIZE . '.');
        }

        $this->term = $term;
        $this->page = $page;
        $this->pageSize = $pageSize;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }
}
