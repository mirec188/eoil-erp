<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

/**
 * The sign-in could not be completed. Messages are fixed texts; no token, code or upstream body.
 */
final class SignInFailed extends RuntimeException
{
    /** eOil refused the user: blocked account or no ERP role. */
    public const DENIED = 'denied';
    /** The code/state was invalid, expired or already used; the user may simply try again. */
    public const REJECTED = 'rejected';
    /** eOil unreachable, misconfigured client or invalid response. */
    public const UNAVAILABLE = 'unavailable';

    private function __construct(public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }

    public static function denied(): self
    {
        return new self(self::DENIED, 'eOil refused access for this user.');
    }

    public static function rejected(string $reason): self
    {
        return new self(self::REJECTED, 'Sign-in rejected: ' . $reason . '.');
    }

    public static function unavailable(string $reason): self
    {
        return new self(self::UNAVAILABLE, 'Sign-in unavailable: ' . $reason . '.');
    }
}
