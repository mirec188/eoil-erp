<?php

declare(strict_types=1);

namespace App\Web\Catalog;

use App\Catalog\CatalogGateway;
use App\Catalog\CatalogUnavailable;
use App\Catalog\InvalidCatalogQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class ListAction
{
    public function __construct(
        private CatalogGateway $gateway,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $query = CatalogRequest::query($request->getQueryParams());
        } catch (InvalidCatalogQuery) {
            return $this->viewRenderer
                ->render(__DIR__ . '/invalid', [
                    'heading' => 'Neplatné vyhľadávanie',
                    'message' => 'Hľadaný výraz môže mať najviac 200 znakov a strana musí byť kladné celé číslo.',
                    'backLabel' => 'Späť na katalóg',
                    'backUrl' => $this->urlGenerator->generate('catalog/list'),
                ])
                ->withStatus(Status::BAD_REQUEST);
        }

        $listParameters = CatalogRequest::listParameters($query);

        try {
            $page = $this->gateway->search($query);
        } catch (CatalogUnavailable $e) {
            $this->logger->warning('Catalog list unavailable: ' . $e->getMessage());
            return $this->viewRenderer
                ->render(__DIR__ . '/unavailable', [
                    'retryUrl' => $this->urlGenerator->generate('catalog/list', [], $listParameters),
                    'breadcrumbs' => [],
                ])
                ->withStatus(Status::SERVICE_UNAVAILABLE);
        }

        return $this->viewRenderer->render(__DIR__ . '/list', [
            'query' => $query,
            'page' => $page,
            'listParameters' => $listParameters,
        ]);
    }
}
