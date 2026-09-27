<?php

declare(strict_types=1);

namespace App\Web\Shared\Access;

use App\Catalog\CatalogSettings;
use App\Shared\Access\DevelopmentAccessPolicy;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;

/**
 * Enforces DevelopmentAccessPolicy on every request, including /health and assets served by the app.
 */
final readonly class AccessPolicyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private DevelopmentAccessPolicy $policy,
        private CatalogSettings $catalogSettings,
        private ResponseFactoryInterface $responseFactory,
        private string $appEnv,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $decision = $this->policy->decide($this->appEnv, $this->catalogSettings->source);
        if ($decision->allowed) {
            return $handler->handle($request);
        }

        $response = $this->responseFactory
            ->createResponse(Status::SERVICE_UNAVAILABLE)
            ->withHeader(Header::CONTENT_TYPE, 'text/plain; charset=UTF-8')
            ->withHeader(Header::CACHE_CONTROL, 'no-store');
        $response->getBody()->write("Prístup odmietnutý.\n" . $decision->reason . "\n");

        return $response;
    }
}
