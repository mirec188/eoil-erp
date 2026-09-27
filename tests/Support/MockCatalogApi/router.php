<?php

declare(strict_types=1);

/**
 * Test-only mock of eOil for the ERP Web tests (PHP built-in server on loopback, tests/Web.suite.yml).
 * Mirrors the M2 endpoints of the eOil worktree (codex/erp-api-identity); it is NOT a real eOil.
 *
 * Control (test only):
 *   GET /__mock/reset?scenario=allowed|denied|guest|token-denied|token-unavailable
 *   GET /__mock/revoke   — every issued token now gets 403 (blocked user / role removed)
 *   GET /__mock/expire   — every issued token now gets 401
 *
 * Catalog scenarios are selected by `q`: mock-500, mock-invalid-json, mock-invalid-item, mock-timeout,
 * mock-empty; anything else returns one synthetic item. Detail: 501 = item, 500 = error, else 404.
 */

const CLIENT_ID = 'eoil-erp';
const CLIENT_SECRET = 'synthetic-client-secret-7f3a-not-a-real-secret';
const REDIRECT_URI = 'http://127.0.0.1:8082/auth/callback';

function respond(int $status, string $body, string $contentType = 'application/json'): never
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

function json(int $status, array $data): never
{
    respond($status, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Read-modify-write of the shared mock state under an exclusive lock. */
function state(?callable $change = null): array
{
    $handle = fopen(sys_get_temp_dir() . '/eoil-erp-mock-eoil.json', 'c+');
    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = json_decode($raw === '' ? '{}' : $raw, true) ?: [];
    $state += ['scenario' => 'allowed', 'codes' => [], 'tokens' => []];
    if ($change !== null) {
        $state = $change($state);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

function challengeFor(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

function redirectToClient(array $params): never
{
    header('Location: ' . REDIRECT_URI . '?' . http_build_query($params), true, 302);
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

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// --- test control -------------------------------------------------------------------------------
if ($path === '/__mock/reset') {
    $scenario = (string) ($_GET['scenario'] ?? 'allowed');
    state(static fn() => ['scenario' => $scenario, 'codes' => [], 'tokens' => []]);
    respond(200, 'ok', 'text/plain');
}
if ($path === '/__mock/revoke' || $path === '/__mock/expire') {
    $status = $path === '/__mock/revoke' ? 'revoked' : 'expired';
    state(static function (array $s) use ($status) {
        foreach ($s['tokens'] as $token => $_) {
            $s['tokens'][$token] = $status;
        }
        return $s;
    });
    respond(200, 'ok', 'text/plain');
}

// --- authorize (browser) ------------------------------------------------------------------------
if ($path === '/erp-auth/authorize' && $method === 'GET') {
    if (($_GET['client_id'] ?? '') !== CLIENT_ID || ($_GET['redirect_uri'] ?? '') !== REDIRECT_URI) {
        respond(400, 'Neplatná požiadavka na prihlásenie do ERP.', 'text/plain; charset=utf-8');
    }
    $state = (string) ($_GET['state'] ?? '');
    $challenge = (string) ($_GET['code_challenge'] ?? '');
    if (($_GET['code_challenge_method'] ?? '') !== 'S256' || preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) !== 1) {
        redirectToClient(['error' => 'invalid_request', 'state' => $state]);
    }
    $scenario = state()['scenario'];
    if ($scenario === 'guest') {
        respond(200, '<p>Najprv sa prihláste do eOil (mock).</p>', 'text/html; charset=utf-8');
    }
    if ($scenario === 'denied') {
        redirectToClient(['error' => 'access_denied', 'state' => $state]);
    }
    $code = bin2hex(random_bytes(16));
    state(static function (array $s) use ($code, $challenge) {
        $s['codes'][$code] = $challenge;
        return $s;
    });
    redirectToClient(['code' => $code, 'state' => $state]);
}

// --- token (server to server) -------------------------------------------------------------------
if ($path === '/erp-api/v1/auth/token') {
    if ($method !== 'POST') {
        json(404, ['error' => 'not_found']);
    }
    $expected = 'Basic ' . base64_encode(urlencode(CLIENT_ID) . ':' . urlencode(CLIENT_SECRET));
    if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== $expected) {
        json(401, ['error' => 'invalid_client']);
    }
    if (($_POST['grant_type'] ?? '') !== 'authorization_code' || ($_POST['redirect_uri'] ?? '') !== REDIRECT_URI) {
        json(400, ['error' => 'invalid_request']);
    }
    $code = (string) ($_POST['code'] ?? '');
    $challenge = null;
    $s = state(static function (array $s) use ($code, &$challenge) {
        $challenge = $s['codes'][$code] ?? null;
        unset($s['codes'][$code]);
        return $s;
    });
    if ($challenge === null || !hash_equals($challenge, challengeFor((string) ($_POST['code_verifier'] ?? '')))) {
        json(400, ['error' => 'invalid_grant']);
    }
    if ($s['scenario'] === 'token-denied') {
        json(403, ['error' => 'access_denied']);
    }
    if ($s['scenario'] === 'token-unavailable') {
        respond(500, '<h1>RAW-UPSTREAM-BODY</h1>', 'text/html');
    }
    $token = 'mock-access-' . bin2hex(random_bytes(16));
    state(static function (array $s) use ($token) {
        $s['tokens'][$token] = 'active';
        return $s;
    });
    json(200, [
        'token_type' => 'Bearer',
        'access_token' => $token,
        'expires_in' => 600,
        'user' => ['id' => 7001, 'displayName' => 'Testovací používateľ (mock)', 'roles' => ['Admin']],
    ]);
}

// --- catalog API (bearer) -----------------------------------------------------------------------
if (!str_starts_with($path, '/erp-api/v1/product-packs') || $method !== 'GET') {
    json(404, ['error' => 'not_found']);
}
$bearer = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token = str_starts_with($bearer, 'Bearer ') ? substr($bearer, 7) : '';
$tokenStatus = state()['tokens'][$token] ?? null;
if ($tokenStatus === null || $tokenStatus === 'expired') {
    json(401, ['error' => 'invalid_token']);
}
if ($tokenStatus === 'revoked') {
    json(403, ['error' => 'forbidden']);
}

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
        default => json(404, ['error' => 'not_found']),
    };
}

json(404, ['error' => 'not_found']);
