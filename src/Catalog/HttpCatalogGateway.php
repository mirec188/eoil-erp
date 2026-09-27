<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Shared\Http\InvalidJsonResponse;
use App\Shared\Http\JsonResponseReader;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

use function array_is_list;
use function array_key_exists;
use function http_build_query;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function rtrim;

/**
 * HTTP adapter for the eOil ERP catalog API (see docs/development/catalog-api-contract.md).
 *
 * Implemented on the eOil side in M2 (branch codex/erp-api-identity, not deployed). Every request carries
 * the access token of the signed-in user. GET only, no retries. 401 means the sign-in is no longer
 * valid, 403 that the user may no longer use the ERP; every other unexpected status, transport error or
 * malformed/invalid payload is CatalogUnavailable — never an empty catalog.
 */
final class HttpCatalogGateway implements CatalogGateway
{
    private const COLLECTION_PATH = '/erp-api/v1/product-packs';
    public const MAX_BODY_BYTES = JsonResponseReader::MAX_BODY_BYTES;

    private readonly string $baseUrl;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        string $baseUrl,
        private readonly AccessTokenProvider $tokens,
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
        return ['baseUrl' => $this->baseUrl];
    }

    private function send(string $pathAndQuery): ResponseInterface
    {
        $request = $this->requestFactory
            ->createRequest('GET', $this->baseUrl . $pathAndQuery)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->tokens->accessToken())
            ->withHeader('User-Agent', 'eoil-erp');

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface) {
            // The client exception may carry the request (with headers) and raw details; drop it.
            throw CatalogUnavailable::transport();
        }

        return match ($response->getStatusCode()) {
            401 => throw new CatalogAuthenticationRequired(),
            403 => throw new CatalogAccessDenied(),
            default => $response,
        };
    }

    private function decode(ResponseInterface $response): mixed
    {
        try {
            return JsonResponseReader::decode($response);
        } catch (InvalidJsonResponse $e) {
            throw CatalogUnavailable::invalidResponse($e->getMessage());
        }
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
