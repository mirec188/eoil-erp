<?php

declare(strict_types=1);

use App\Auth\AuthSession;
use App\Auth\EoilSignInSettings;
use App\Catalog\CatalogSettings;
use App\Shared\ApplicationParams;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Definitions\Reference;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;

return [
    'application' => require __DIR__ . '/application.php',

    'yiisoft/aliases' => [
        'aliases' => require __DIR__ . '/aliases.php',
    ],

    'yiisoft/view' => [
        'basePath' => null,
        'parameters' => [
            'assetManager' => Reference::to(AssetManager::class),
            'applicationParams' => Reference::to(ApplicationParams::class),
            'aliases' => Reference::to(Aliases::class),
            'urlGenerator' => Reference::to(UrlGeneratorInterface::class),
            'currentRoute' => Reference::to(CurrentRoute::class),
            'catalogSettings' => Reference::to(CatalogSettings::class),
            'signInSettings' => Reference::to(EoilSignInSettings::class),
            'authSession' => Reference::to(AuthSession::class),
        ],
    ],

    // Session cookie: HttpOnly, SameSite=Lax (the eOil callback is a top-level GET navigation),
    // strict mode rejects unknown IDs. `cookie_secure` is off for local HTTP development only;
    // an HTTPS deployment must set it to 1 (production is refused in this slice anyway).
    'yiisoft/session' => [
        'session' => [
            'options' => [
                'name' => 'ERPSESSID',
                'cookie_httponly' => 1,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => 0,
                'use_strict_mode' => 1,
                'use_only_cookies' => 1,
                'gc_maxlifetime' => 3600,
            ],
            'handler' => null,
        ],
    ],

    'yiisoft/yii-view-renderer' => [
        'viewPath' => null,
        'layout' => '@src/Web/Shared/Layout/Main/layout.php',
        'injections' => [
            Reference::to(CsrfViewInjection::class),
        ],
    ],
];
