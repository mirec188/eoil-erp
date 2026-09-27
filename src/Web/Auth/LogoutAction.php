<?php

declare(strict_types=1);

namespace App\Web\Auth;

use App\Auth\AuthSession;
use Psr\Http\Message\ResponseInterface;

/**
 * POST /logout (CSRF-protected by CsrfTokenMiddleware) — ends the ERP sign-in.
 * The eOil session stays; single logout is outside M2.
 */
final readonly class LogoutAction
{
    public function __construct(
        private AuthSession $authSession,
        private SignInResponses $responses,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $this->authSession->signOut('signed-out');
        return $this->responses->redirect('/login');
    }
}
