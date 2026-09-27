<?php

declare(strict_types=1);

namespace App\Auth;

use function base64_encode;
use function hash;
use function random_bytes;
use function rtrim;
use function strtr;

/**
 * PKCE S256 (RFC 7636) and state values from a CSPRNG.
 */
final class Pkce
{
    /** 43 base64url characters (32 random bytes). */
    public static function randomToken(): string
    {
        return self::base64Url(random_bytes(32));
    }

    public static function challengeFor(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
