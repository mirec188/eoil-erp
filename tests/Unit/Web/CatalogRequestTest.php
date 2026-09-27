<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web;

use App\Catalog\InvalidCatalogQuery;
use App\Web\Catalog\CatalogRequest;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class CatalogRequestTest extends Unit
{
    public function testDefaults(): void
    {
        $query = CatalogRequest::query([]);

        assertSame('', $query->term);
        assertSame(1, $query->page);
    }

    public function testParsesTermAndPage(): void
    {
        $query = CatalogRequest::query(['q' => ' 901.01 ', 'page' => '2', 'utm' => 'ignored']);

        assertSame('901.01', $query->term);
        assertSame(2, $query->page);
    }

    public function testEmptyPageMeansFirstPage(): void
    {
        assertSame(1, CatalogRequest::query(['page' => ''])->page);
    }

    /**
     * @return iterable<string, array{array}>
     */
    public static function invalidParams(): iterable
    {
        yield 'q array' => [['q' => ['x']]];
        yield 'page array' => [['page' => ['1']]];
        yield 'page zero' => [['page' => '0']];
        yield 'page negative' => [['page' => '-1']];
        yield 'page decimal' => [['page' => '1.5']];
        yield 'page letters' => [['page' => 'abc']];
        yield 'page leading zero' => [['page' => '01']];
        yield 'page overflow' => [['page' => '99999999999999999999']];
        yield 'page above max' => [['page' => '10001']];
        yield 'q too long' => [['q' => str_repeat('a', 201)]];
    }

    #[DataProvider('invalidParams')]
    public function testRejectsInvalidParams(array $params): void
    {
        $this->expectException(InvalidCatalogQuery::class);
        CatalogRequest::query($params);
    }

    public function testListParametersOmitDefaults(): void
    {
        assertSame([], CatalogRequest::listParameters(CatalogRequest::query([])));
        assertSame(
            ['q' => 'olej', 'page' => 3],
            CatalogRequest::listParameters(CatalogRequest::query(['q' => 'olej', 'page' => '3'])),
        );
    }

    public function testReturnParametersDropInvalidOrForeignInput(): void
    {
        assertSame([], CatalogRequest::returnParameters(['q' => 'x', 'page' => '0']));
        assertSame([], CatalogRequest::returnParameters(['return' => 'https://evil.example']));
        assertSame(['q' => 'x', 'page' => 2], CatalogRequest::returnParameters(['q' => 'x', 'page' => '2', 'return' => '//evil']));
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function ids(): iterable
    {
        yield 'valid' => ['101', 101];
        yield 'zero' => ['0', null];
        yield 'negative' => ['-3', null];
        yield 'leading zero' => ['0101', null];
        yield 'exponent' => ['1e3', null];
        yield 'letters' => ['abc', null];
        yield 'overflow' => ['99999999999999999999', null];
        yield 'max 18 digits' => ['999999999999999999', 999999999999999999];
    }

    #[DataProvider('ids')]
    public function testId(string $raw, ?int $expected): void
    {
        if ($expected === null) {
            assertNull(CatalogRequest::id($raw));
        } else {
            assertSame($expected, CatalogRequest::id($raw));
        }
    }
}
