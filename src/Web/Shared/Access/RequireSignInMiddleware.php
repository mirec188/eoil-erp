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
 * signed-in eOil user. Guests are sent to /login; the requested local path is kept for afterwards.
 * There is no bypass switch.
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

        $target = $path . ($request->getUri()->getQuery() === '' ? '' : '?' . $request->getUri()->getQuery());
        $return = AuthSession::safeReturnPath($target);

        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, '/login' . ($return === '/' ? '' : '?' . http_build_query(['return' => $return])))
            ->withHeader(Header::CACHE_CONTROL, 'no-store');
    }
}
