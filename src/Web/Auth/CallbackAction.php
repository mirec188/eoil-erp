<?php

declare(strict_types=1);

namespace App\Web\Auth;

use App\Auth\AuthSession;
use App\Auth\EoilSignInSettings;
use App\Auth\EoilTokenClient;
use App\Auth\SignInFailed;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function is_string;

/**
 * GET /auth/callback — checks state, exchanges the one-time code server-to-server and signs in.
 * Neither the code nor the token is logged or shown.
 */
final readonly class CallbackAction
{
    public function __construct(
        private EoilSignInSettings $settings,
        private AuthSession $authSession,
        private EoilTokenClient $tokenClient,
        private SignInResponses $responses,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->settings->enabled) {
            return $this->responses->redirect('/');
        }
        $params = $request->getQueryParams();
        $state = self::stringParam($params, 'state');
        $code = self::stringParam($params, 'code');
        $error = self::stringParam($params, 'error');

        $pending = $state === '' ? null : $this->authSession->takePendingSignIn($state);
        if ($pending === null) {
            return $this->responses->failed();
        }
        if ($error === 'access_denied') {
            $this->authSession->signOut();
            return $this->responses->denied();
        }
        if ($error !== '' || $code === '') {
            return $this->responses->failed();
        }

        try {
            $grant = $this->tokenClient->exchange($code, $pending['verifier']);
        } catch (SignInFailed $e) {
            $this->logger->warning('eOil sign-in failed: ' . $e->getMessage());
            return match ($e->kind) {
                SignInFailed::DENIED => $this->responses->denied(),
                SignInFailed::REJECTED => $this->responses->failed(),
                default => $this->responses->unavailable(),
            };
        }

        $this->authSession->completeSignIn($grant);
        $this->logger->info('eOil user ' . $grant->user->id . ' signed in to the ERP.');

        return $this->responses->redirect($pending['returnPath']);
    }

    private static function stringParam(array $params, string $name): string
    {
        $value = $params[$name] ?? null;
        return is_string($value) ? $value : '';
    }
}
