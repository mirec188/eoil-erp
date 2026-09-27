<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web;

use App\Auth\AuthSession;
use App\Auth\EoilSignInSettings;
use App\Auth\SignedInUser;
use App\Auth\TokenGrant;
use App\Catalog\CatalogSettings;
use App\Tests\Support\InMemorySession;
use App\Web\Shared\Access\RequireSignInMiddleware;
use Codeception\Test\Unit;
use HttpSoft\Message\Response;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function PHPUnit\Framework\assertSame;

final class RequireSignInMiddlewareTest extends Unit
{
    private InMemorySession $session;
    private AuthSession $auth;

    protected function _before(): void
    {
        $this->session = new InMemorySession();
        $this->auth = new AuthSession($this->session);
    }

    private function middleware(): RequireSignInMiddleware
    {
        $catalog = CatalogSettings::fromEnvironment('test', 'http', 'http://127.0.0.1:8092', null);
        $settings = EoilSignInSettings::fromEnvironment(
            'test',
            $catalog,
            'http://127.0.0.1:8092/erp-auth/authorize',
            'eoil-erp',
            'synthetic-client-secret-7f3a-not-a-real-secret',
            'http://127.0.0.1:8082/auth/callback',
        );
        return new RequireSignInMiddleware($settings, $this->auth, new ResponseFactory());
    }

    private function handle(string $method, string $uri): ResponseInterface
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };
        return $this->middleware()->process(new ServerRequest(method: $method, uri: $uri), $handler);
    }

    private function signInThenExpire(): void
    {
        $this->auth->completeSignIn(new TokenGrant('synthetic-access-token-0123456789', time() - 1, new SignedInUser(7, 'Test', ['Admin'])));
    }

    public function testGuestGetGoesToLoginWithReturn(): void
    {
        $response = $this->handle('GET', '/catalog?q=olej');
        assertSame(302, $response->getStatusCode());
        assertSame('/login?return=%2Fcatalog%3Fq%3Dolej', $response->getHeaderLine('Location'));
    }

    public function testExpiredSignInRenewsAutomaticallyOnGet(): void
    {
        $this->signInThenExpire();
        $response = $this->handle('GET', '/catalog/501?q=x');
        assertSame('/login/start?return=%2Fcatalog%2F501%3Fq%3Dx', $response->getHeaderLine('Location'));

        // Second request right after: no loop, the login page instead.
        assertSame('/login?return=%2Fcatalog', $this->handle('GET', '/catalog')->getHeaderLine('Location'));
    }

    public function testPostIsNeverRenewedOrReplayed(): void
    {
        $this->signInThenExpire();
        assertSame('/login', $this->handle('POST', '/catalog?q=x')->getHeaderLine('Location'));
    }

    public function testNoAutomaticRenewalAfterExplicitSignOut(): void
    {
        $this->signInThenExpire();
        $this->auth->currentUser();
        $this->auth->signOut('signed-out');
        assertSame('/login?return=%2Fcatalog', $this->handle('GET', '/catalog')->getHeaderLine('Location'));
    }

    public function testPublicPathsPassWithoutSignIn(): void
    {
        foreach (['/login', '/login/start', '/auth/callback', '/logout', '/health'] as $path) {
            assertSame(200, $this->handle('GET', $path)->getStatusCode(), $path);
        }
    }
}
