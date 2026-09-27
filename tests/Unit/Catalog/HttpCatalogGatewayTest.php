<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\CatalogQuery;
use App\Catalog\CatalogUnavailable;
use App\Catalog\HttpCatalogGateway;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use HttpSoft\Message\RequestFactory;
use Psr\Http\Message\RequestInterface;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringNotContainsString;
use function PHPUnit\Framework\assertTrue;

final class HttpCatalogGatewayTest extends Unit
{
    private const BASE_URL = 'https://eoil.example.test';
    private const TOKEN = 'synthetic-token-91b2-not-a-secret';

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    public function testValidCollection(): void
    {
        $gateway = $this->gateway(self::json([
            'items' => [
                self::item(101, ['901.01']),
                self::item(102, ['901.02', '0904.10'], unit: null, active: false),
            ],
            'total' => 2,
            'page' => 1,
            'pageSize' => 25,
        ]));

        $page = $gateway->search(new CatalogQuery('901.01'));

        assertCount(2, $page->items);
        assertSame(2, $page->total);
        assertSame(101, $page->items[0]->id);
        assertSame(['901.01'], $page->items[0]->mrpNumbers);
        assertSame(['901.02', '0904.10'], $page->items[1]->mrpNumbers);
        assertNull($page->items[1]->unit);
        assertSame(false, $page->items[1]->active);
    }

    public function testEmptyItemsIsValidResult(): void
    {
        $page = $this->gateway(self::json(['items' => [], 'total' => 0, 'page' => 1, 'pageSize' => 25]))
            ->search(new CatalogQuery('nič'));

        assertTrue($page->isEmpty());
    }

    public function testRequestShapeEncodingAndAuthorization(): void
    {
        $gateway = $this->gateway(self::json(['items' => [], 'total' => 0, 'page' => 2, 'pageSize' => 25]));

        $gateway->search(new CatalogQuery('olej & filter', 2));

        assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        assertSame('GET', $request->getMethod());
        assertSame('/erp-api/v1/product-packs', $request->getUri()->getPath());
        assertSame('q=olej%20%26%20filter&page=2&pageSize=25', $request->getUri()->getQuery());
        assertSame('Bearer ' . self::TOKEN, $request->getHeaderLine('Authorization'));
        assertSame('application/json', $request->getHeaderLine('Accept'));
        assertStringNotContainsString(self::TOKEN, (string) $request->getUri());
    }

    public function testDetail(): void
    {
        $gateway = $this->gateway(self::json(self::item(101, ['901.01'])));

        $view = $gateway->get(101);

        assertNotNull($view);
        assertSame(['901.01'], $view->mrpNumbers);
        assertSame('/erp-api/v1/product-packs/101', $this->history[0]['request']->getUri()->getPath());
    }

    public function testDetail404IsNotFound(): void
    {
        assertNull($this->gateway(new Response(404))->get(999999));
    }

    /**
     * @return iterable<string, array{Response|ConnectException}>
     */
    public static function failingSearchResponses(): iterable
    {
        $collection = static fn(array $overrides): Response => self::json(array_replace(
            ['items' => [self::item(101, ['901.01'])], 'total' => 1, 'page' => 1, 'pageSize' => 25],
            $overrides,
        ));

        yield '401' => [new Response(401, [], '{"error":"unauthorized"}')];
        yield '403' => [new Response(403)];
        yield '404 on collection' => [new Response(404)];
        yield '500' => [new Response(500, ['Content-Type' => 'text/html'], '<h1>RAW UPSTREAM BODY</h1>')];
        yield '503' => [new Response(503)];
        yield 'redirect' => [new Response(302, ['Location' => 'https://elsewhere.example.test/'])];
        yield 'malformed JSON' => [new Response(200, ['Content-Type' => 'application/json'], '{"items": [')];
        yield 'not JSON content type' => [new Response(200, ['Content-Type' => 'text/html'], '{}')];
        yield 'JSON scalar' => [self::json('ok')];
        yield 'items missing' => [self::json(['total' => 0, 'page' => 1, 'pageSize' => 25])];
        yield 'item without id' => [$collection(['items' => [array_diff_key(self::item(101, []), ['id' => 1])]])];
        yield 'id as string' => [$collection(['items' => [['id' => '101'] + self::item(101, [])]])];
        yield 'id zero' => [$collection(['items' => [self::item(0, [])]])];
        yield 'mrpNumbers as number' => [$collection(['items' => [['mrpNumbers' => 901.01] + self::item(101, [])]])];
        yield 'mrp number as float' => [$collection(['items' => [['mrpNumbers' => [901.01]] + self::item(101, [])]])];
        yield 'active as string' => [$collection(['items' => [['active' => 'yes'] + self::item(101, [])]])];
        yield 'unit key missing' => [$collection(['items' => [array_diff_key(self::item(101, []), ['unit' => 1])]])];
        yield 'total as string' => [$collection(['total' => '1'])];
        yield 'other page than requested' => [$collection(['page' => 2, 'total' => 30])];
        yield 'other page size' => [$collection(['pageSize' => 50])];
        yield 'total below items' => [$collection(['total' => 0])];
        yield 'empty items although total says items exist' => [$collection(['items' => [], 'total' => 5])];
        yield 'huge total with too few items' => [$collection(['total' => PHP_INT_MAX])];
        yield 'timeout' => [new ConnectException(
            'cURL error 28: Operation timed out; Authorization: Bearer ' . self::TOKEN,
            new \GuzzleHttp\Psr7\Request('GET', self::BASE_URL),
        )];
    }

    #[DataProvider('failingSearchResponses')]
    public function testFailuresAreUnavailableNotEmpty(Response|ConnectException $response): void
    {
        $gateway = $this->gateway($response);

        try {
            $gateway->search(new CatalogQuery());
            $this->fail('Expected CatalogUnavailable, a failure must never look like an empty catalog.');
        } catch (CatalogUnavailable $e) {
            assertStringNotContainsString(self::TOKEN, $e->getMessage());
            assertStringNotContainsString('RAW UPSTREAM BODY', $e->getMessage());
            assertNull($e->getPrevious());
        }
        // No retries.
        assertCount(1, $this->history);
    }

    /**
     * @return iterable<string, array{Response}>
     */
    public static function failingDetailResponses(): iterable
    {
        yield '401' => [new Response(401)];
        yield '500' => [new Response(500)];
        yield 'malformed JSON' => [new Response(200, ['Content-Type' => 'application/json'], 'nope')];
        yield 'other id than requested' => [self::json(self::item(102, []))];
        yield 'collection instead of item' => [self::json(['items' => [], 'total' => 0, 'page' => 1, 'pageSize' => 25])];
    }

    #[DataProvider('failingDetailResponses')]
    public function testDetailFailuresAreUnavailable(Response $response): void
    {
        $this->expectException(CatalogUnavailable::class);
        $this->gateway($response)->get(101);
    }

    public function testOversizedBodyWithKnownSizeIsRejected(): void
    {
        $body = '{"items":[],"total":0,"page":1,"pageSize":25,"pad":"'
            . str_repeat('a', HttpCatalogGateway::MAX_BODY_BYTES) . '"}';

        $this->expectExceptionMessage('body is too large');
        $this->gateway(new Response(200, ['Content-Type' => 'application/json'], $body))->search(new CatalogQuery());
    }

    public function testEndlessBodyOfUnknownSizeIsReadOnlyUpToLimit(): void
    {
        $bytesRead = 0;
        $stream = FnStream::decorate(Utils::streamFor(''), [
            'getSize' => static fn(): ?int => null,
            'isSeekable' => static fn(): bool => false,
            'eof' => static fn(): bool => false,
            'read' => static function (int $length) use (&$bytesRead): string {
                $bytesRead += $length;
                return str_repeat('a', $length);
            },
        ]);

        try {
            $this->gateway(new Response(200, ['Content-Type' => 'application/json'], $stream))
                ->search(new CatalogQuery());
            $this->fail('Expected CatalogUnavailable.');
        } catch (CatalogUnavailable $e) {
            assertSame('Catalog API returned an invalid response: body is too large.', $e->getMessage());
        }
        assertSame(HttpCatalogGateway::MAX_BODY_BYTES + 1, $bytesRead);
    }

    public function testStreamFailureIsSanitized(): void
    {
        $stream = FnStream::decorate(Utils::streamFor('{}'), [
            'read' => static function (): string {
                throw new \RuntimeException('RAW-UPSTREAM-BODY Bearer ' . self::TOKEN);
            },
        ]);

        try {
            $this->gateway(new Response(200, ['Content-Type' => 'application/json'], $stream))->get(101);
            $this->fail('Expected CatalogUnavailable.');
        } catch (CatalogUnavailable $e) {
            assertSame('Catalog API returned an invalid response: body could not be read.', $e->getMessage());
            assertNull($e->getPrevious());
        }
    }

    public function testTokenIsNotExposedByDebugOutput(): void
    {
        $gateway = $this->gateway(new Response(500));

        assertStringNotContainsString(self::TOKEN, print_r($gateway, true));
    }

    private function gateway(Response|ConnectException $response): HttpCatalogGateway
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->history));

        return new HttpCatalogGateway(
            new Client(['handler' => $stack, 'http_errors' => false, 'allow_redirects' => false]),
            new RequestFactory(),
            self::BASE_URL,
            self::TOKEN,
        );
    }

    private static function json(mixed $data): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode($data, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param list<mixed> $mrpNumbers
     */
    private static function item(int $id, array $mrpNumbers, ?string $unit = 'l', bool $active = true): array
    {
        return [
            'id' => $id,
            'name' => 'Ukážkový olej',
            'packLabel' => '1 l',
            'unit' => $unit,
            'active' => $active,
            'mrpNumbers' => $mrpNumbers,
        ];
    }
}
