<?php

declare(strict_types=1);

namespace App\Auth;

use SensitiveParameter;

/**
 * Result of a successful code exchange. The access token stays on the server (session) only.
 */
final readonly class TokenGrant
{
    public function __construct(
        #[SensitiveParameter]
        public string $accessToken,
        public int $expiresAt,
        public SignedInUser $user,
    ) {}

    public function __debugInfo(): array
    {
        return ['accessToken' => '***', 'expiresAt' => $this->expiresAt, 'user' => $this->user];
    }
}
