<?php

declare(strict_types=1);

namespace App\Auth;

use App\Catalog\AccessTokenProvider;
use App\Catalog\CatalogAuthenticationRequired;
use Yiisoft\Session\SessionInterface;

use function hash_equals;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function str_starts_with;
use function strlen;
use function time;

/**
 * ERP sign-in state in the server-side session. The browser only holds the session cookie.
 *
 * - pending sign-in: state, PKCE verifier and the local return path, valid for 10 minutes;
 * - signed-in user: eOil user reference, display name, ERP roles, access token and its expiry.
 *   The ERP session ends at the latest when the eOil access token expires.
 */
final class AuthSession implements AccessTokenProvider
{
    private const PENDING = 'erp.auth.pending';
    private const USER = 'erp.auth.user';
    private const NOTICE = 'erp.auth.notice';
    private const PENDING_TTL_SECONDS = 600;
    private const NOTICES = ['expired', 'denied', 'failed', 'unavailable', 'signed-out'];
    private const NON_RETURN_PREFIXES = ['/login', '/auth/', '/logout', '/health'];

    public function __construct(private readonly SessionInterface $session) {}

    /**
     * Starts a sign-in and returns the values for the authorize request.
     *
     * @return array{state: string, codeChallenge: string}
     */
    public function beginSignIn(string $returnPath, ?int $now = null): array
    {
        $state = Pkce::randomToken();
        $verifier = Pkce::randomToken();
        $this->session->set(self::PENDING, [
            'state' => $state,
            'verifier' => $verifier,
            'returnPath' => self::safeReturnPath($returnPath),
            'createdAt' => $now ?? time(),
        ]);

        return ['state' => $state, 'codeChallenge' => Pkce::challengeFor($verifier)];
    }

    /**
     * Consumes the pending sign-in if the state from the callback matches (constant-time comparison).
     *
     * @return array{verifier: string, returnPath: string}|null
     */
    public function takePendingSignIn(string $state, ?int $now = null): ?array
    {
        $pending = $this->session->pull(self::PENDING);
        if (!is_array($pending)) {
            return null;
        }
        $expected = $pending['state'] ?? null;
        $verifier = $pending['verifier'] ?? null;
        $createdAt = $pending['createdAt'] ?? null;
        $returnPath = $pending['returnPath'] ?? null;
        if (
            !is_string($expected)
            || !is_string($verifier)
            || !is_int($createdAt)
            || $createdAt + self::PENDING_TTL_SECONDS < ($now ?? time())
            || !hash_equals($expected, $state)
        ) {
            return null;
        }

        return [
            'verifier' => $verifier,
            'returnPath' => self::safeReturnPath(is_string($returnPath) ? $returnPath : '/'),
        ];
    }

    /** New session ID on privilege change (session fixation), then store the user. */
    public function completeSignIn(TokenGrant $grant): void
    {
        $this->session->regenerateId();
        $this->session->set(self::USER, [
            'id' => $grant->user->id,
            'displayName' => $grant->user->displayName,
            'roles' => $grant->user->roles,
            'accessToken' => $grant->accessToken,
            'expiresAt' => $grant->expiresAt,
        ]);
    }

    public function currentUser(?int $now = null): ?SignedInUser
    {
        $stored = $this->storedUser($now);
        return $stored === null ? null : new SignedInUser($stored['id'], $stored['displayName'], $stored['roles']);
    }

    public function accessToken(): string
    {
        $stored = $this->storedUser();
        if ($stored === null) {
            throw new CatalogAuthenticationRequired();
        }
        return $stored['accessToken'];
    }

    /**
     * Ends the ERP sign-in. The eOil session is not affected (no single logout in M2).
     */
    public function signOut(?string $notice = null): void
    {
        $this->session->remove(self::USER);
        $this->session->remove(self::PENDING);
        if ($this->session->isActive()) {
            $this->session->regenerateId();
        }
        if ($notice !== null) {
            $this->setNotice($notice);
        }
    }

    public function setNotice(string $notice): void
    {
        if (in_array($notice, self::NOTICES, true)) {
            $this->session->set(self::NOTICE, $notice);
        }
    }

    public function pullNotice(): ?string
    {
        $notice = $this->session->pull(self::NOTICE);
        return is_string($notice) && in_array($notice, self::NOTICES, true) ? $notice : null;
    }

    /**
     * Only a local absolute path of this application; anything else becomes "/".
     */
    public static function safeReturnPath(string $path): string
    {
        if (
            $path === ''
            || strlen($path) > 1000
            || !str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || preg_match('~^/[^\x00-\x20\x7f\\\\]*$~', $path) !== 1
        ) {
            return '/';
        }
        foreach (self::NON_RETURN_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return '/';
            }
        }
        return $path;
    }

    /**
     * @return array{id: int, displayName: string, roles: list<string>, accessToken: string, expiresAt: int}|null
     */
    private function storedUser(?int $now = null): ?array
    {
        $user = $this->session->get(self::USER);
        if (
            !is_array($user)
            || !is_int($user['id'] ?? null)
            || !is_string($user['displayName'] ?? null)
            || !is_array($user['roles'] ?? null)
            || !is_string($user['accessToken'] ?? null)
            || !is_int($user['expiresAt'] ?? null)
        ) {
            return null;
        }
        if ($user['expiresAt'] <= ($now ?? time())) {
            $this->session->remove(self::USER);
            $this->setNotice('expired');
            return null;
        }
        /** @var array{id: int, displayName: string, roles: list<string>, accessToken: string, expiresAt: int} */
        return $user;
    }
}
