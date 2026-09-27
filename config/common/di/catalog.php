<?php

declare(strict_types=1);

use App\Catalog\CatalogGateway;
use App\Catalog\CatalogSettings;
use App\Catalog\CatalogSource;
use App\Catalog\FixtureCatalogGateway;
use App\Catalog\HttpCatalogGateway;
use App\Environment;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestFactoryInterface;

return [
    CatalogSettings::class => static fn(): CatalogSettings => CatalogSettings::fromEnvironment(
        appEnv: Environment::appEnv(),
        source: Environment::catalogSource(),
        apiBaseUrl: Environment::catalogApiBaseUrl(),
        apiToken: Environment::catalogApiToken(),
        timeout: Environment::catalogApiTimeout(),
    ),

    CatalogGateway::class => static function (
        CatalogSettings $settings,
        RequestFactoryInterface $requestFactory,
    ): CatalogGateway {
        if ($settings->source === CatalogSource::Fixture) {
            return new FixtureCatalogGateway();
        }

        // PSR-18 client with finite timeouts. No retries; redirects are refused so the
        // bearer token is never forwarded to another location.
        $client = new Client([
            RequestOptions::TIMEOUT => $settings->timeout,
            RequestOptions::CONNECT_TIMEOUT => min($settings->timeout, 3.0),
            RequestOptions::READ_TIMEOUT => $settings->timeout,
            // Stream the body so HttpCatalogGateway's size limit actually bounds memory.
            RequestOptions::STREAM => true,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
        ]);

        return new HttpCatalogGateway(
            $client,
            $requestFactory,
            (string) $settings->apiBaseUrl,
            $settings->apiToken(),
        );
    },
];
