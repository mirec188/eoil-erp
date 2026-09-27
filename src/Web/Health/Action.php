<?php

declare(strict_types=1);

namespace App\Web\Health;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;

/**
 * Minimal liveness probe. Deliberately exposes no environment, configuration or version details.
 */
final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse(Status::OK)
            ->withHeader(Header::CONTENT_TYPE, 'application/json')
            ->withHeader(Header::CACHE_CONTROL, 'no-store');
        $response->getBody()->write('{"status":"ok"}');

        return $response;
    }
}
