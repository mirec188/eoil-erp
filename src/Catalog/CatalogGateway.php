<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Read-only port to the eOil product-pack catalog. Deliberately has no save/delete operations:
 * ProductHasPack is owned by eOil.
 */
interface CatalogGateway
{
    /**
     * @throws CatalogUnavailable When the source fails; never returned as an empty page.
     */
    public function search(CatalogQuery $query): CatalogPage;

    /**
     * @throws InvalidCatalogQuery When the ID is not positive.
     * @throws CatalogUnavailable When the source fails.
     *
     * @return ProductPackView|null Null only when the ProductHasPack does not exist.
     */
    public function get(int $id): ?ProductPackView;
}
