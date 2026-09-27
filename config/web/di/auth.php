<?php

declare(strict_types=1);

use App\Auth\AuthSession;
use App\Auth\EoilSignInSettings;
use App\Auth\EoilTokenClient;
use App\Catalog\AccessTokenProvider;
use App\Shared\Http\EoilHttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Definitions\Reference;

return [
    AuthSession::class => AuthSession::class,
    AccessTokenProvider::class => Reference::to(AuthSession::class),

    EoilTokenClient::class => static fn(
        EoilSignInSettings $settings,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ): EoilTokenClient => new EoilTokenClient(
        EoilHttpClient::create($settings->timeout),
        $requestFactory,
        $streamFactory,
        $settings,
    ),
];
