<?php

declare(strict_types=1);

/**
 * Test-only mock of the *proposed* eOil ERP catalog API (docs/development/catalog-api-contract.md).
 * Run with the PHP built-in server on loopback (see tests/Web.suite.yml). Not a real eOil endpoint.
 *
 * Collection scenarios are selected by `q`: mock-500, mock-401, mock-invalid-json, mock-invalid-item,
 * mock-timeout, mock-empty; anything else returns one synthetic item.
 * Detail: 501 = item, 500 = server error, anything else = 404.
 */

const EXPECTED_TOKEN = 'synthetic-token-7f3a-not-a-secret';

function respond(int $status, string $body, string $contentType = 'application/json'): never
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    echo $body;
    exit;
}

function item(): array
{
    return [
        'id' => 501,
        'name' => 'Mock API: testovací olej',
        'packLabel' => '1 l',
        'unit' => 'l',
        'active' => true,
        'mrpNumbers' => ['990.01'],
    ];
}

if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer ' . EXPECTED_TOKEN) {
    respond(401, '{"error":"RAW-UPSTREAM-BODY unauthorized"}');
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, '{"error":"method not allowed"}');
}

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/erp-api/v1/product-packs') {
    $q = (string) ($_GET['q'] ?? '');
    $page = (int) ($_GET['page'] ?? 1);
    $pageSize = (int) ($_GET['pageSize'] ?? 25);
    $collection = static fn(array $items, int $total): string => json_encode(
        ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    );

    match ($q) {
        'mock-500' => respond(500, '<h1>RAW-UPSTREAM-BODY Internal error</h1>', 'text/html'),
        'mock-401' => respond(401, '{"error":"RAW-UPSTREAM-BODY"}'),
        'mock-invalid-json' => respond(200, '{"items": [RAW-UPSTREAM-BODY'),
        'mock-invalid-item' => respond(200, $collection([['mrpNumbers' => [990.01]] + item()], 1)),
        'mock-empty' => respond(200, $collection([], 0)),
        'mock-timeout' => (static function () use ($collection): never {
            sleep(2);
            respond(200, $collection([item()], 1));
        })(),
        default => respond(200, $page === 1 ? $collection([item()], 1) : $collection([], 1)),
    };
}

if (preg_match('~^/erp-api/v1/product-packs/(\d+)$~', $path, $matches) === 1) {
    match ($matches[1]) {
        '501' => respond(200, json_encode(item(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
        '500' => respond(500, 'RAW-UPSTREAM-BODY', 'text/plain'),
        default => respond(404, '{"error":"not found"}'),
    };
}

respond(404, '{"error":"not found"}');
