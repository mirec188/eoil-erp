<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\AuthSession;
use App\Auth\Pkce;
use App\Auth\SignedInUser;
use App\Auth\TokenGrant;
use App\Catalog\CatalogAuthenticationRequired;
use App\Tests\Support\InMemorySession;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;

use function PHPUnit\Framework\assertMatchesRegularExpression;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

final class AuthSessionTest extends Unit
{
    private const TOKEN = 'synthetic-access-token-0123456789';

    private function grant(int $expiresAt): TokenGrant
    {
        return new TokenGrant(self::TOKEN, $expiresAt, new SignedInUser(7, 'Test User', ['Admin']));
    }

    public function testPendingSignInIsBoundToStateAndUsableOnce(): void
    {
        $auth = new AuthSession(new InMemorySession());
        $request = $auth->beginSignIn('/catalog?q=olej', 1000);

        assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $request['state']);
        assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $request['codeChallenge']);

        $pending = $auth->takePendingSignIn($request['state'], 1010);
        assertSame('/catalog?q=olej', $pending['returnPath']);
        assertSame($request['codeChallenge'], Pkce::challengeFor($pending['verifier']));
        assertNull($auth->takePendingSignIn($request['state'], 1011), 'second use');
    }

    public function testWrongStateConsumesPendingSignIn(): void
    {
        $auth = new AuthSession(new InMemorySession());
        $request = $auth->beginSignIn('/', 1000);

        assertNull($auth->takePendingSignIn('forged', 1001));
        assertNull($auth->takePendingSignIn($request['state'], 1002), 'a failed attempt ends the pending sign-in');
    }

    public function testPendingSignInExpires(): void
    {
        $auth = new AuthSession(new InMemorySession());
        $request = $auth->beginSignIn('/', 1000);

        assertNull($auth->takePendingSignIn($request['state'], 1000 + 601));
    }

    public function testCompleteSignInRegeneratesSessionId(): void
    {
        $session = new InMemorySession();
        $auth = new AuthSession($session);
        $session->open();
        $before = $session->getId();

        $auth->completeSignIn($this->grant(time() + 600));

        assertSame(1, $session->regenerations);
        assertNotSame($before, $session->getId());
        assertSame(7, $auth->currentUser()?->id);
        assertSame(self::TOKEN, $auth->accessToken());
    }

    public function testExpiredSignInIsGoneWithNotice(): void
    {
        $auth = new AuthSession(new InMemorySession());
        $auth->completeSignIn($this->grant(2000));

        assertSame('Test User', $auth->currentUser(1999)?->displayName);
        assertNull($auth->currentUser(2000));
        assertSame('expired', $auth->pullNotice());
        assertNull($auth->pullNotice());
    }

    public function testAccessTokenWithoutSignInThrows(): void
    {
        $this->expectException(CatalogAuthenticationRequired::class);
        (new AuthSession(new InMemorySession()))->accessToken();
    }

    public function testSignOutRemovesUserAndRegeneratesId(): void
    {
        $session = new InMemorySession();
        $auth = new AuthSession($session);
        $auth->completeSignIn($this->grant(time() + 600));

        $auth->signOut('signed-out');

        assertNull($auth->currentUser());
        assertSame(2, $session->regenerations);
        assertSame('signed-out', $auth->pullNotice());
    }

    public function testUnknownNoticeIsIgnored(): void
    {
        $auth = new AuthSession(new InMemorySession());
        $auth->setNotice('<script>');
        assertNull($auth->pullNotice());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function returnPaths(): iterable
    {
        yield 'catalog with query' => ['/catalog?q=olej&page=2', '/catalog?q=olej&page=2'];
        yield 'root' => ['/', '/'];
        yield 'protocol relative' => ['//evil.example/x', '/'];
        yield 'absolute url' => ['https://evil.example/', '/'];
        yield 'backslash' => ['/\\evil.example', '/'];
        yield 'relative' => ['catalog', '/'];
        yield 'newline' => ["/catalog\r\nX: y", '/'];
        yield 'login loop' => ['/login?return=/x', '/'];
        yield 'callback' => ['/auth/callback?code=x', '/'];
        yield 'empty' => ['', '/'];
    }

    #[DataProvider('returnPaths')]
    public function testReturnPathIsLocalOnly(string $input, string $expected): void
    {
        assertSame($expected, AuthSession::safeReturnPath($input));
    }
}
