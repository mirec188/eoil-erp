<?php

declare(strict_types=1);

namespace App\Web\Auth;

use App\Auth\AuthSession;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

use function http_build_query;
use function in_array;

/**
 * Shared responses of the sign-in flow and of pages that meet an expired or refused sign-in.
 */
final readonly class SignInResponses
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private WebViewRenderer $viewRenderer,
        private AuthSession $authSession,
    ) {}

    public function redirect(string $location): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $location)
            ->withHeader(Header::CACHE_CONTROL, 'no-store');
    }

    /**
     * eOil answered 401 during a page: the sign-in is gone. A GET page renews once automatically
     * through the standard authorize flow and comes back here; anything else goes to /login.
     */
    public function signInExpired(ServerRequestInterface $request): ResponseInterface
    {
        $this->authSession->expireSignIn();
        $isRead = in_array($request->getMethod(), ['GET', 'HEAD'], true);
        $uri = $request->getUri();
        $return = $isRead
            ? AuthSession::safeReturnPath($uri->getPath() . ($uri->getQuery() === '' ? '' : '?' . $uri->getQuery()))
            : '/';
        $entry = $isRead && $this->authSession->beginAutomaticRenewal() ? '/login/start' : '/login';

        return $this->redirect($entry . ($return === '/' ? '' : '?' . http_build_query(['return' => $return])));
    }

    /** eOil answered 403: blocked account or no ERP role. The ERP session is ended. */
    public function accessRevoked(): ResponseInterface
    {
        $this->authSession->signOut();
        return $this->denied();
    }

    public function denied(): ResponseInterface
    {
        return $this->result(
            Status::FORBIDDEN,
            'Prístup zamietnutý',
            'Váš účet v eOil nemá oprávnenie používať ERP alebo je zablokovaný. Ak ho potrebujete, obráťte sa na administrátora eOil.',
        );
    }

    public function failed(): ResponseInterface
    {
        return $this->result(
            Status::BAD_REQUEST,
            'Prihlásenie sa nepodarilo',
            'Pokus o prihlásenie je neplatný alebo už vypršal. Skúste sa prihlásiť znova.',
        );
    }

    public function unavailable(): ResponseInterface
    {
        return $this->result(
            Status::SERVICE_UNAVAILABLE,
            'Prihlásenie je dočasne nedostupné',
            'eOil sa nepodarilo kontaktovať alebo vrátil neplatnú odpoveď. Skúste to o chvíľu znova.',
        );
    }

    private function result(int $status, string $heading, string $message): ResponseInterface
    {
        return $this->viewRenderer
            ->render(__DIR__ . '/result', ['heading' => $heading, 'message' => $message])
            ->withStatus($status)
            ->withHeader(Header::CACHE_CONTROL, 'no-store');
    }
}
