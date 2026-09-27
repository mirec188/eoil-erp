<?php

declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SensitiveParameter;

use function array_is_list;
use function array_key_exists;
use function http_build_query;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function min;
use function rtrim;
use function str_contains;
use function strlen;
use function strtolower;

/**
 * HTTP adapter for the *proposed* eOil ERP catalog API (see docs/development/catalog-api-contract.md).
 *
 * The endpoint does not exist in eOil yet (M2); this adapter is verified only against test transports
 * and a local mock. It performs GET requests only, never retries, and treats every unexpected status,
 * transport error or malformed/invalid payload as CatalogUnavailable — never as an empty catalog.
 */
final class HttpCatalogGateway implements CatalogGateway
{
    private const COLLECTION_PATH = '/erp-api/v1/product-packs';
    public const MAX_BODY_BYTES = 2_000_000;
    private const READ_CHUNK_BYTES = 65_536;
    private const JSON_DEPTH = 16;

    private readonly string $baseUrl;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        string $baseUrl,
        #[SensitiveParameter]
        private readonly string $token,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function search(CatalogQuery $query): CatalogPage
    {
        $response = $this->send(self::COLLECTION_PATH . '?' . http_build_query(
            ['q' => $query->term, 'page' => $query->page, 'pageSize' => $query->pageSize],
            '',
            '&',
            PHP_QUERY_RFC3986,
        ));

        if ($response->getStatusCode() !== 200) {
            throw CatalogUnavailable::httpStatus($response->getStatusCode());
        }

        $data = $this->decode($response);
        if (!is_array($data) || array_is_list($data)) {
            throw CatalogUnavailable::invalidResponse('collection is not an object');
        }
        $items = $data['items'] ?? null;
        $total = $data['total'] ?? null;
        $page = $data['page'] ?? null;
        $pageSize = $data['pageSize'] ?? null;

        if (!is_array($items) || !array_is_list($items)) {
            throw CatalogUnavailable::invalidResponse('items is not a list');
        }
        if (!is_int($total) || !is_int($page) || !is_int($pageSize)) {
            throw CatalogUnavailable::invalidResponse('pagination fields are not integers');
        }
        if ($page !== $query->page || $pageSize !== $query->pageSize) {
            throw CatalogUnavailable::invalidResponse('pagination does not match the request');
        }

        $views = [];
        foreach ($items as $item) {
            $views[] = $this->mapItem($item);
        }

        try {
            return new CatalogPage($views, $total, $page, $pageSize);
        } catch (InvalidArgumentException) {
            throw CatalogUnavailable::invalidResponse('inconsistent pagination');
        }
    }

    public function get(int $id): ?ProductPackView
    {
        if ($id <= 0) {
            throw new InvalidCatalogQuery('ProductHasPack ID must be a positive integer.');
        }

        $response = $this->send(self::COLLECTION_PATH . '/' . $id);

        if ($response->getStatusCode() === 404) {
            return null;
        }
        if ($response->getStatusCode() !== 200) {
            throw CatalogUnavailable::httpStatus($response->getStatusCode());
        }

        $view = $this->mapItem($this->decode($response));
        if ($view->id !== $id) {
            throw CatalogUnavailable::invalidResponse('detail ID does not match the request');
        }

        return $view;
    }

    /**
     * Keeps the token out of var_dump()/print_r()/debug output.
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'token' => '***'];
    }

    private function send(string $pathAndQuery): ResponseInterface
    {
        $request = $this->requestFactory
            ->createRequest('GET', $this->baseUrl . $pathAndQuery)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->token)
            ->withHeader('User-Agent', 'eoil-erp');

        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface) {
            // The client exception may carry the request (with headers) and raw details; drop it.
            throw CatalogUnavailable::transport();
        }
    }

    private function decode(ResponseInterface $response): mixed
    {
        if (!str_contains(strtolower($response->getHeaderLine('Content-Type')), 'application/json')) {
            throw CatalogUnavailable::invalidResponse('content type is not application/json');
        }

        $body = $this->readBody($response);

        try {
            return json_decode($body, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw CatalogUnavailable::invalidResponse('malformed JSON');
        }
    }

    /**
     * Reads at most MAX_BODY_BYTES + 1 bytes, so an oversized or endless body never fills memory
     * (the client is configured to stream the body). Stream errors become a sanitized failure;
     * their messages are never propagated.
     */
    private function readBody(ResponseInterface $response): string
    {
        $body = '';
        try {
            $stream = $response->getBody();
            $size = $stream->getSize();
            if ($size === null || $size <= self::MAX_BODY_BYTES) {
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                while (strlen($body) <= self::MAX_BODY_BYTES && !$stream->eof()) {
                    $chunk = $stream->read(min(self::READ_CHUNK_BYTES, self::MAX_BODY_BYTES + 1 - strlen($body)));
                    if ($chunk === '') {
                        break;
                    }
                    $body .= $chunk;
                }
            }
        } catch (RuntimeException) {
            throw CatalogUnavailable::invalidResponse('body could not be read');
        }

        if (($size ?? 0) > self::MAX_BODY_BYTES || strlen($body) > self::MAX_BODY_BYTES) {
            throw CatalogUnavailable::invalidResponse('body is too large');
        }

        return $body;
    }

    private function mapItem(mixed $item): ProductPackView
    {
        if (!is_array($item) || array_is_list($item)) {
            throw CatalogUnavailable::invalidResponse('item is not an object');
        }

        $id = $item['id'] ?? null;
        $name = $item['name'] ?? null;
        $packLabel = $item['packLabel'] ?? null;
        $unit = $item['unit'] ?? null;
        $active = $item['active'] ?? null;
        $mrpNumbers = $item['mrpNumbers'] ?? null;

        if (
            !is_int($id)
            || !is_string($name)
            || !is_string($packLabel)
            || !array_key_exists('unit', $item)
            || ($unit !== null && !is_string($unit))
            || !is_bool($active)
            || !is_array($mrpNumbers)
        ) {
            throw CatalogUnavailable::invalidResponse('item fields have unexpected types');
        }

        try {
            return new ProductPackView($id, $name, $packLabel, $unit, $active, $mrpNumbers);
        } catch (InvalidArgumentException) {
            throw CatalogUnavailable::invalidResponse('item fields have invalid values');
        }
    }
}
