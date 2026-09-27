<?php

declare(strict_types=1);

namespace App\Web\Shared\Layout\Main;

use Yiisoft\Assets\AssetBundle;

/**
 * Limitless v4.0 / Bootstrap 5.2.2 / Phosphor — the subset of the eOil newadmin theme the ERP uses.
 * All files are vendored locally (no CDN, no Google Fonts); see docs/development/theme-provenance.json.
 * jQuery and Yii2 yii.js are intentionally not included.
 */
final class LimitlessAsset extends AssetBundle
{
    public ?string $basePath = '@assets';
    public ?string $baseUrl = '@assetsUrl';
    public ?string $sourcePath = '@assetsSource/limitless';

    public array $css = [
        'fonts/inter/inter.css',
        'icons/phosphor/styles.min.css',
        'css/all.min.css',
    ];

    public array $js = [
        'js/bootstrap/bootstrap.bundle.min.js',
    ];
}
