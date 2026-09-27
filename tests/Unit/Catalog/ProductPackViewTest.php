<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\CatalogPage;
use App\Catalog\CatalogQuery;
use App\Catalog\InvalidCatalogQuery;
use App\Catalog\ProductPackView;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use InvalidArgumentException;

use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

final class ProductPackViewTest extends Unit
{
    public function testKeepsIdentityAndMrpStringsExactly(): void
    {
        $view = new ProductPackView(101, 'Ukážkový olej', '1 l', 'l', true, ['901.01', '0904.10', '903']);

        assertSame(101, $view->id);
        assertSame(['901.01', '0904.10', '903'], $view->mrpNumbers);
    }

    public function testUnitMayBeNullAndMrpListEmpty(): void
    {
        $view = new ProductPackView(108, 'Ukážková sada', '1 sada', null, false, []);

        assertNull($view->unit);
        assertFalse($view->active);
        assertSame([], $view->mrpNumbers);
    }

    /**
     * @return iterable<string, array{int, string, string, ?string, array}>
     */
    public static function invalidViews(): iterable
    {
        yield 'zero id' => [0, 'Olej', '1 l', 'l', ['901.01']];
        yield 'negative id' => [-5, 'Olej', '1 l', 'l', ['901.01']];
        yield 'blank name' => [1, '  ', '1 l', 'l', []];
        yield 'blank pack label' => [1, 'Olej', '', 'l', []];
        yield 'blank unit' => [1, 'Olej', '1 l', '', []];
        yield 'mrp as float' => [1, 'Olej', '1 l', 'l', [901.01]];
        yield 'mrp as int' => [1, 'Olej', '1 l', 'l', [903]];
        yield 'mrp empty string' => [1, 'Olej', '1 l', 'l', ['']];
        yield 'mrp padded' => [1, 'Olej', '1 l', 'l', [' 901.01']];
        yield 'mrp not a list' => [1, 'Olej', '1 l', 'l', ['a' => '901.01']];
    }

    #[DataProvider('invalidViews')]
    public function testRejectsInvalidData(int $id, string $name, string $pack, ?string $unit, array $mrp): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProductPackView($id, $name, $pack, $unit, true, $mrp);
    }

    public function testQueryDefaults(): void
    {
        $query = new CatalogQuery();

        assertSame('', $query->term);
        assertSame(1, $query->page);
        assertSame(25, $query->pageSize);
        assertSame(0, $query->offset());
    }

    public function testQueryTrimsTermAndComputesOffset(): void
    {
        $query = new CatalogQuery('  901.01 ', 3);

        assertSame('901.01', $query->term);
        assertSame(50, $query->offset());
    }

    public function testQueryAcceptsTwoHundredCharacters(): void
    {
        $query = new CatalogQuery(str_repeat('ž', 200));

        assertSame(200, mb_strlen($query->term));
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'page zero' => ['', 0, 25];
        yield 'negative page' => ['', -1, 25];
        yield 'page too high' => ['', CatalogQuery::MAX_PAGE + 1, 25];
        yield 'page size zero' => ['', 1, 0];
        yield 'page size other than 25' => ['', 1, 100];
        yield 'term too long' => [str_repeat('a', 201), 1, 25];
        yield 'term invalid utf-8' => ["\xC3\x28", 1, 25];
    }

    #[DataProvider('invalidQueries')]
    public function testQueryRejectsInvalidInput(string $term, int $page, int $pageSize): void
    {
        $this->expectException(InvalidCatalogQuery::class);
        new CatalogQuery($term, $page, $pageSize);
    }

    public function testPageNavigation(): void
    {
        $items = array_fill(0, 7, new ProductPackView(126, 'Ukážka', '1 l', 'l', true, []));
        $page = new CatalogPage($items, 32, 2, 25);

        assertSame(2, $page->pageCount());
        assertTrue($page->hasPrevious());
        assertFalse($page->hasNext());
        assertFalse($page->isEmpty());
    }

    public function testEmptyPage(): void
    {
        $page = new CatalogPage([], 0, 1, 25);

        assertTrue($page->isEmpty());
        assertSame(1, $page->pageCount());
        assertFalse($page->hasNext());
    }

    public function testReviewedOverflowCaseIsControlledValidationError(): void
    {
        // Previously pageCount() overflowed to float and threw TypeError.
        $this->expectException(InvalidArgumentException::class);
        new CatalogPage([], PHP_INT_MAX, 1, 25);
    }

    public function testFullPageWithHugeTotalIsValid(): void
    {
        $items = array_fill(0, 25, new ProductPackView(1, 'Ukážka', '1 l', 'l', true, []));
        $page = new CatalogPage($items, PHP_INT_MAX, 1, 25);

        assertSame(intdiv(PHP_INT_MAX, 25) + 1, $page->pageCount());
        assertTrue($page->hasNext());
    }

    public function testPageBeyondTotalIsEmpty(): void
    {
        $page = new CatalogPage([], 32, 9, 25);

        assertTrue($page->isEmpty());
        assertSame(2, $page->pageCount());
    }

    /**
     * @return iterable<string, array{array, int, int, int}>
     */
    public static function invalidPages(): iterable
    {
        $item = new ProductPackView(1, 'Ukážka', '1 l', 'l', true, []);
        yield 'negative total' => [[], -1, 1, 25];
        yield 'page zero' => [[], 0, 0, 25];
        yield 'more items than page size' => [array_fill(0, 26, $item), 26, 1, 25];
        yield 'total below returned items' => [[$item, $item], 1, 1, 25];
        yield 'total below offset' => [[$item], 10, 2, 25];
        yield 'item of wrong type' => [['x'], 1, 1, 25];
        yield 'empty page although total says items exist' => [[], 30, 1, 25];
        yield 'empty second page although total says items exist' => [[], 30, 2, 25];
        yield 'fewer items than a full page' => [[$item], 30, 1, 25];
        yield 'page size other than 25' => [[], 0, 1, 10];
        yield 'page above maximum' => [[], PHP_INT_MAX, CatalogQuery::MAX_PAGE + 1, 25];
    }

    #[DataProvider('invalidPages')]
    public function testPageRejectsInconsistentData(array $items, int $total, int $page, int $pageSize): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CatalogPage($items, $total, $page, $pageSize);
    }
}
