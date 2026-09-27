<?php

declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;

use function array_is_list;
use function count;
use function intdiv;
use function max;
use function min;

final readonly class CatalogPage
{
    /**
     * @param list<ProductPackView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException('Items must be a list.');
        }
        foreach ($items as $item) {
            if (!$item instanceof ProductPackView) {
                throw new InvalidArgumentException('Items must be ProductPackView instances.');
            }
        }
        if ($total < 0) {
            throw new InvalidArgumentException('Total must be >= 0.');
        }
        if ($page < 1 || $page > CatalogQuery::MAX_PAGE) {
            throw new InvalidArgumentException('Page must be between 1 and ' . CatalogQuery::MAX_PAGE . '.');
        }
        if ($pageSize !== CatalogQuery::PAGE_SIZE) {
            throw new InvalidArgumentException('Page size must be ' . CatalogQuery::PAGE_SIZE . '.');
        }
        // Bounded page/pageSize keep the offset small; total may be any non-negative int.
        $offset = ($page - 1) * $pageSize;
        $expected = $total <= $offset ? 0 : min($pageSize, $total - $offset);
        if (count($items) !== $expected) {
            throw new InvalidArgumentException('Number of items does not match total, page and page size.');
        }
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function pageCount(): int
    {
        // Overflow-safe ceiling division (total may be PHP_INT_MAX).
        $pages = intdiv($this->total, $this->pageSize) + ($this->total % $this->pageSize === 0 ? 0 : 1);
        return max(1, $pages);
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pageCount();
    }
}
