<?php

declare(strict_types=1);

use App\Auth\EoilSignInSettings;
use App\Catalog\AccessTokenProvider;
use App\Catalog\CatalogGateway;
use App\Catalog\CatalogSettings;
use App\Catalog\CatalogSource;
use App\Catalog\FixtureCatalogGateway;
use App\Catalog\HttpCatalogGateway;
use App\Environment;
use App\Shared\Http\EoilHttpClient;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestFactoryInterface;

return [
    CatalogSettings::class => static fn(): CatalogSettings => CatalogSettings::fromEnvironment(
        appEnv: Environment::appEnv(),
        source: Environment::catalogSource(),
        apiBaseUrl: Environment::eoilApiBaseUrl(),
        timeout: Environment::catalogApiTimeout(),
    ),

    EoilSignInSettings::class => static fn(CatalogSettings $catalog): EoilSignInSettings => EoilSignInSettings::fromEnvironment(
        appEnv: Environment::appEnv(),
        catalog: $catalog,
        authorizeUrl: Environment::eoilAuthorizeUrl(),
        clientId: Environment::eoilClientId(),
        clientSecret: Environment::eoilClientSecret(),
        redirectUri: Environment::erpRedirectUri(),
    ),

    CatalogGateway::class => static function (
        CatalogSettings $settings,
        RequestFactoryInterface $requestFactory,
        ContainerInterface $container,
    ): CatalogGateway {
        if ($settings->source === CatalogSource::Fixture) {
            return new FixtureCatalogGateway();
        }

        /** @var AccessTokenProvider $tokens the signed-in user's session (web only) */
        $tokens = $container->get(AccessTokenProvider::class);

        return new HttpCatalogGateway(
            EoilHttpClient::create($settings->timeout),
            $requestFactory,
            (string) $settings->apiBaseUrl,
            $tokens,
        );
    },
];
