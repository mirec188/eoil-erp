<?php

declare(strict_types=1);

namespace App\Web\Auth;

use App\Auth\AuthSession;
use App\Auth\EoilSignInSettings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function http_build_query;
use function is_string;
use function str_contains;

/** GET /login/start — creates state + PKCE and sends the browser to the eOil authorize page. */
final readonly class StartAction
{
    public function __construct(
        private EoilSignInSettings $settings,
        private AuthSession $authSession,
        private SignInResponses $responses,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->settings->enabled) {
            return $this->responses->redirect('/');
        }
        $return = $request->getQueryParams()['return'] ?? '/';
        $pending = $this->authSession->beginSignIn(is_string($return) ? $return : '/');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->settings->clientId,
            'redirect_uri' => $this->settings->redirectUri,
            'state' => $pending['state'],
            'code_challenge' => $pending['codeChallenge'],
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
        $separator = str_contains($this->settings->authorizeUrl, '?') ? '&' : '?';

        return $this->responses->redirect($this->settings->authorizeUrl . $separator . $query);
    }
}
