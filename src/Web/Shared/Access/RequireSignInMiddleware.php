<?php

declare(strict_types=1);

namespace App\Web\Shared\Access;

use App\Auth\AuthSession;
use App\Auth\EoilSignInSettings;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;

use function http_build_query;
use function in_array;

/**
 * When the ERP reads real eOil data, every page except the sign-in flow and /health requires a
 * signed-in eOil user. There is no bypass switch.
 *
 * - GET/HEAD after an expired sign-in: one automatic renewal through the standard authorize/PKCE flow
 *   (/login/start), back to the same local GET address (AuthSession::beginAutomaticRenewal);
 * - otherwise: /login. Only GET/HEAD addresses are kept as return path; other methods are never replayed.
 */
final readonly class RequireSignInMiddleware implements MiddlewareInterface
{
    private const PUBLIC_PATHS = ['/login', '/login/start', '/auth/callback', '/logout', '/health'];

    public function __construct(
        private EoilSignInSettings $settings,
        private AuthSession $authSession,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!$this->settings->enabled || in_array($path, self::PUBLIC_PATHS, true)) {
            return $handler->handle($request);
        }
        if ($this->authSession->currentUser() !== null) {
            return $handler->handle($request);
        }

        $isRead = in_array($request->getMethod(), ['GET', 'HEAD'], true);
        $return = '/';
        if ($isRead) {
            $query = $request->getUri()->getQuery();
            $return = AuthSession::safeReturnPath($path . ($query === '' ? '' : '?' . $query));
        }
        $entry = $isRead && $this->authSession->beginAutomaticRenewal() ? '/login/start' : '/login';

        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $entry . ($return === '/' ? '' : '?' . http_build_query(['return' => $return])))
            ->withHeader(Header::CACHE_CONTROL, 'no-store');
    }
}
