<?php

declare(strict_types=1);

namespace App\Web\Catalog;

use App\Catalog\CatalogQuery;
use App\Catalog\InvalidCatalogQuery;

use function is_string;
use function preg_match;

/**
 * Parses and validates catalog request parameters before any gateway call.
 * Only `q` and `page` are recognised; every other parameter (including any "return URL") is ignored.
 */
final class CatalogRequest
{
    /**
     * @throws InvalidCatalogQuery
     */
    public static function query(array $params): CatalogQuery
    {
        $term = $params['q'] ?? '';
        if (!is_string($term)) {
            throw new InvalidCatalogQuery('Parameter q must be a single value.');
        }

        $page = $params['page'] ?? '';
        if (!is_string($page)) {
            throw new InvalidCatalogQuery('Parameter page must be a single value.');
        }
        if ($page === '') {
            $page = '1';
        }
        if (preg_match('/^[1-9]\d{0,4}$/', $page) !== 1) {
            throw new InvalidCatalogQuery('Parameter page must be a positive integer.');
        }

        return new CatalogQuery($term, (int) $page);
    }

    /**
     * Query parameters that reproduce the list state; defaults are omitted.
     *
     * @return array{q?: string, page?: int}
     */
    public static function listParameters(CatalogQuery $query): array
    {
        $params = [];
        if ($query->term !== '') {
            $params['q'] = $query->term;
        }
        if ($query->page > 1) {
            $params['page'] = $query->page;
        }
        return $params;
    }

    /**
     * Safe list state for "back to list" links from a detail page: invalid input yields the plain list.
     *
     * @return array{q?: string, page?: int}
     */
    public static function returnParameters(array $params): array
    {
        try {
            return self::listParameters(self::query($params));
        } catch (InvalidCatalogQuery) {
            return [];
        }
    }

    /**
     * Canonical positive ProductHasPack ID from a path segment, or null when it is not one.
     */
    public static function id(string $raw): ?int
    {
        return preg_match('/^[1-9]\d{0,17}$/', $raw) === 1 ? (int) $raw : null;
    }
}
