<?php

declare(strict_types=1);

namespace App\Web\Catalog;

use App\Catalog\CatalogAccessDenied;
use App\Catalog\CatalogAuthenticationRequired;
use App\Catalog\CatalogGateway;
use App\Catalog\CatalogUnavailable;
use App\Web\Auth\SignInResponses;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Http\Status;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class DetailAction
{
    public function __construct(
        private CatalogGateway $gateway,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private SignInResponses $signIn,
    ) {}

    public function __invoke(ServerRequestInterface $request, CurrentRoute $currentRoute): ResponseInterface
    {
        // Only q/page are carried back to the list, validated; never an arbitrary return URL.
        $returnParameters = CatalogRequest::returnParameters($request->getQueryParams());
        $backUrl = $this->urlGenerator->generate('catalog/list', [], $returnParameters);
        $breadcrumbs = [['label' => 'Katalóg', 'url' => $backUrl]];

        $id = CatalogRequest::id((string) $currentRoute->getArgument('id'));
        if ($id === null) {
            return $this->viewRenderer
                ->render(__DIR__ . '/invalid', [
                    'heading' => 'Neplatné ID',
                    'message' => 'ID balenia musí byť kladné celé číslo bez úvodných núl.',
                    'backLabel' => 'Späť na zoznam',
                    'backUrl' => $backUrl,
                ])
                ->withStatus(Status::BAD_REQUEST);
        }

        try {
            $view = $this->gateway->get($id);
        } catch (CatalogAuthenticationRequired) {
            return $this->signIn->signInExpired($request);
        } catch (CatalogAccessDenied) {
            return $this->signIn->accessRevoked();
        } catch (CatalogUnavailable $e) {
            $this->logger->warning('Catalog detail unavailable: ' . $e->getMessage());
            return $this->viewRenderer
                ->render(__DIR__ . '/unavailable', [
                    'retryUrl' => $this->urlGenerator->generate('catalog/detail', ['id' => (string) $id], $returnParameters),
                    'breadcrumbs' => $breadcrumbs,
                ])
                ->withStatus(Status::SERVICE_UNAVAILABLE);
        }

        if ($view === null) {
            return $this->viewRenderer
                ->render(__DIR__ . '/not-found', [
                    'id' => $id,
                    'backUrl' => $backUrl,
                    'breadcrumbs' => $breadcrumbs,
                ])
                ->withStatus(Status::NOT_FOUND);
        }

        return $this->viewRenderer->render(__DIR__ . '/detail', [
            'view' => $view,
            'backUrl' => $backUrl,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
