<?php

declare(strict_types=1);

namespace App\Web\Shared\Layout\Main;

use Yiisoft\Assets\AssetBundle;

/**
 * ERP customizations on top of the newadmin theme (loaded after Limitless so overrides win).
 */
final class MainAsset extends AssetBundle
{
    public ?string $basePath = '@assets';
    public ?string $baseUrl = '@assetsUrl';
    public ?string $sourcePath = '@assetsSource/erp';

    public array $css = [
        'custom.css',
        'navigation.css',
    ];

    public array $depends = [
        LimitlessAsset::class,
    ];
}
