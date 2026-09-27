<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\CatalogQuery;
use App\Catalog\FixtureCatalogGateway;
use App\Catalog\InvalidCatalogQuery;
use App\Catalog\ProductPackView;
use Codeception\Test\Unit;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertGreaterThanOrEqual;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertTrue;

final class FixtureCatalogGatewayTest extends Unit
{
    public function testExactMrpNumberFindsProductPack101(): void
    {
        $gateway = new FixtureCatalogGateway();
        $page = $gateway->search(new CatalogQuery('901.01'));

        assertCount(1, $page->items);
        assertSame(['901.01'], $page->items[0]->mrpNumbers);
        assertSame(101, $page->items[0]->id);
        assertNull($gateway->get(999999));
    }

    public function testMrpMatchIsExactNotPrefix(): void
    {
        $gateway = new FixtureCatalogGateway();

        assertSame(0, $gateway->search(new CatalogQuery('901'))->total);
        assertSame(0, $gateway->search(new CatalogQuery('901.1'))->total);
        assertSame([106], $this->ids($gateway->search(new CatalogQuery('0904.01'))->items));
        assertSame(0, $gateway->search(new CatalogQuery('904.01'))->total);
        assertSame([110], $this->ids($gateway->search(new CatalogQuery('907.10'))->items));
    }

    public function testEmptyTermListsAllInStableIdOrderAcrossTwoPages(): void
    {
        $gateway = new FixtureCatalogGateway();

        $first = $gateway->search(new CatalogQuery('', 1));
        $second = $gateway->search(new CatalogQuery('', 2));

        assertGreaterThanOrEqual(30, $first->total);
        assertCount(25, $first->items);
        assertSame(2, $first->pageCount());
        assertCount($first->total - 25, $second->items);

        $ids = [...$this->ids($first->items), ...$this->ids($second->items)];
        $sorted = $ids;
        sort($sorted);
        assertSame($sorted, $ids);
        assertSame(101, $ids[0]);
        assertSame(126, $second->items[0]->id);
    }

    public function testPageBeyondRangeIsEmptyButKeepsTotal(): void
    {
        $page = (new FixtureCatalogGateway())->search(new CatalogQuery('', 5));

        assertSame([], $page->items);
        assertSame(5, $page->page);
        assertGreaterThanOrEqual(30, $page->total);
    }

    public function testNameSearchIsCaseAndDiacriticsInsensitive(): void
    {
        $gateway = new FixtureCatalogGateway();

        $withDiacritics = $this->ids($gateway->search(new CatalogQuery('prevodový'))->items);
        $withoutDiacritics = $this->ids($gateway->search(new CatalogQuery('PREVODOVY'))->items);

        assertSame([103, 104], $withDiacritics);
        assertSame($withDiacritics, $withoutDiacritics);
        assertSame([110], $this->ids($gateway->search(new CatalogQuery('ťažkých nečistôt'))->items));
    }

    public function testNoResultIsValidEmptyPage(): void
    {
        $page = (new FixtureCatalogGateway())->search(new CatalogQuery('neexistujúci výraz xyz'));

        assertTrue($page->isEmpty());
        assertSame(0, $page->total);
    }

    public function testFixtureCoversRequiredEdgeCases(): void
    {
        $all = $this->all();

        $inactive = array_filter($all, static fn(ProductPackView $v): bool => !$v->active);
        $withoutUnit = array_filter($all, static fn(ProductPackView $v): bool => $v->unit === null);
        $withHtml = array_filter($all, static fn(ProductPackView $v): bool => str_contains($v->name, '<'));

        assertGreaterThanOrEqual(1, count($inactive));
        assertGreaterThanOrEqual(1, count($withoutUnit));
        assertGreaterThanOrEqual(1, count($withHtml));
        foreach ($all as $view) {
            // Names are visibly synthetic, never real export data.
            assertStringContainsString('ukážk', mb_strtolower($view->name));
        }
    }

    public function testGetReturnsDetail(): void
    {
        $view = (new FixtureCatalogGateway())->get(101);

        assertNotNull($view);
        assertSame(101, $view->id);
        assertSame(['901.01'], $view->mrpNumbers);
    }

    public function testGetRejectsNonPositiveId(): void
    {
        $gateway = new FixtureCatalogGateway();

        foreach ([0, -1] as $id) {
            try {
                $gateway->get($id);
                $this->fail("Expected InvalidCatalogQuery for id $id.");
            } catch (InvalidCatalogQuery) {
            }
        }
    }

    /**
     * @return list<ProductPackView>
     */
    private function all(): array
    {
        $gateway = new FixtureCatalogGateway();
        $first = $gateway->search(new CatalogQuery());
        $items = $first->items;
        for ($page = 2; $page <= $first->pageCount(); $page++) {
            $items = [...$items, ...$gateway->search(new CatalogQuery('', $page))->items];
        }
        return $items;
    }

    /**
     * @param list<ProductPackView> $items
     * @return list<int>
     */
    private function ids(array $items): array
    {
        return array_map(static fn(ProductPackView $v): int => $v->id, $items);
    }
}
