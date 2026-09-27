<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\EoilSignInSettings;
use App\Auth\EoilTokenClient;
use App\Auth\SignInFailed;
use App\Catalog\CatalogSettings;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HttpSoft\Message\RequestFactory;
use HttpSoft\Message\StreamFactory;
use Psr\Http\Message\RequestInterface;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertStringNotContainsString;

final class EoilTokenClientTest extends Unit
{
    private const SECRET = 'synthetic-client-secret-with+plus/slash=0123';
    private const TOKEN = 'synthetic.access.token-0123456789';

    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    private function client(Response|ConnectException $response): EoilTokenClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->history));
        $catalog = CatalogSettings::fromEnvironment('test', 'http', 'http://127.0.0.1:8092/backend', null);
        $settings = EoilSignInSettings::fromEnvironment(
            'test',
            $catalog,
            'http://127.0.0.1:8092/erp-auth/authorize',
            'eoil-erp',
            self::SECRET,
            'http://127.0.0.1:8082/auth/callback',
        );

        return new EoilTokenClient(
            new Client(['handler' => $stack, 'http_errors' => false, 'allow_redirects' => false]),
            new RequestFactory(),
            new StreamFactory(),
            $settings,
        );
    }

    private static function json(int $status, array $data): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function success(array $overrides = []): Response
    {
        return self::json(200, $overrides + [
            'token_type' => 'Bearer',
            'access_token' => self::TOKEN,
            'expires_in' => 600,
            'user' => ['id' => 42, 'displayName' => 'Test User', 'roles' => ['Admin']],
        ]);
    }

    public function testSuccessfulExchange(): void
    {
        $grant = $this->client(self::success())->exchange('the-code', 'the-verifier', 1000);

        assertSame(self::TOKEN, $grant->accessToken);
        assertSame(1000 + 600 - 15, $grant->expiresAt);
        assertSame(42, $grant->user->id);
        assertSame(['Admin'], $grant->user->roles);
    }

    public function testRequestShape(): void
    {
        $this->client(self::success())->exchange('the-code', 'the-verifier', 1000);

        assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        assertSame('POST', $request->getMethod());
        assertSame('http://127.0.0.1:8092/backend/erp-api/v1/auth/token', (string) $request->getUri());
        assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        assertSame('Basic ' . base64_encode('eoil-erp:' . urlencode(self::SECRET)), $request->getHeaderLine('Authorization'));
        parse_str((string) $request->getBody(), $form);
        assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'the-code',
            'code_verifier' => 'the-verifier',
            'redirect_uri' => 'http://127.0.0.1:8082/auth/callback',
        ], $form);
        assertStringNotContainsString(self::SECRET, (string) $request->getUri());
        assertStringNotContainsString(self::SECRET, (string) $request->getBody());
    }

    /**
     * @return iterable<string, array{Response|ConnectException, string}>
     */
    public static function failures(): iterable
    {
        yield 'denied' => [self::json(403, ['error' => 'access_denied']), SignInFailed::DENIED];
        yield 'invalid grant' => [self::json(400, ['error' => 'invalid_grant']), SignInFailed::REJECTED];
        yield 'invalid request' => [self::json(400, ['error' => 'invalid_request']), SignInFailed::REJECTED];
        yield 'invalid client' => [self::json(401, ['error' => 'invalid_client']), SignInFailed::UNAVAILABLE];
        yield 'server error html' => [new Response(500, ['Content-Type' => 'text/html'], 'RAW-UPSTREAM-BODY'), SignInFailed::UNAVAILABLE];
        yield 'not configured' => [self::json(503, ['error' => 'temporarily_unavailable']), SignInFailed::UNAVAILABLE];
        yield 'redirect' => [new Response(302, ['Location' => 'https://elsewhere.example']), SignInFailed::UNAVAILABLE];
        yield 'malformed json' => [new Response(200, ['Content-Type' => 'application/json'], '{"access'), SignInFailed::UNAVAILABLE];
        yield 'wrong token type' => [self::success(['token_type' => 'mac']), SignInFailed::UNAVAILABLE];
        yield 'token with space' => [self::success(['access_token' => 'abc def ghi jkl mno pqr']), SignInFailed::UNAVAILABLE];
        yield 'expires as string' => [self::success(['expires_in' => '600']), SignInFailed::UNAVAILABLE];
        yield 'expires too long' => [self::success(['expires_in' => 86400]), SignInFailed::UNAVAILABLE];
        yield 'user id string' => [self::success(['user' => ['id' => '42', 'displayName' => 'X', 'roles' => ['Admin']]]), SignInFailed::UNAVAILABLE];
        yield 'user without roles' => [self::success(['user' => ['id' => 42, 'displayName' => 'X', 'roles' => []]]), SignInFailed::UNAVAILABLE];
        yield 'user missing' => [self::success(['user' => null]), SignInFailed::UNAVAILABLE];
        yield 'timeout' => [new ConnectException('cURL error 28 Authorization: Basic ' . base64_encode('eoil-erp:' . self::SECRET), new Request('POST', 'http://127.0.0.1')), SignInFailed::UNAVAILABLE];
    }

    #[DataProvider('failures')]
    public function testFailuresAreClassifiedAndSanitized(Response|ConnectException $response, string $kind): void
    {
        try {
            $this->client($response)->exchange('the-code', 'the-verifier');
            $this->fail('Expected SignInFailed.');
        } catch (SignInFailed $e) {
            assertSame($kind, $e->kind);
            assertStringNotContainsString(self::SECRET, $e->getMessage());
            assertStringNotContainsString('RAW-UPSTREAM-BODY', $e->getMessage());
            assertStringNotContainsString('the-code', $e->getMessage());
            assertNull($e->getPrevious());
        }
        assertCount(1, $this->history, 'no retries');
    }

    public function testGrantDebugOutputHidesToken(): void
    {
        $grant = $this->client(self::success())->exchange('the-code', 'the-verifier', 1000);
        assertStringNotContainsString(self::TOKEN, print_r($grant, true));
    }
}
