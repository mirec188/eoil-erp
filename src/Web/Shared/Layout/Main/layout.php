<?php

declare(strict_types=1);

use App\Catalog\CatalogSettings;
use App\Web\Shared\Layout\Main\MainAsset;
use Yiisoft\Html\Html;

/**
 * Layout adapted from eOil newadmin (views/layouts/main.php + _navbar_horizontal.php):
 * Limitless horizontal dark navbar, light page header, content area. No Yii2 code.
 *
 * Templates may set view parameters:
 * - `breadcrumbs`: list<array{label: string, url?: string}>
 *
 * @var \App\Shared\ApplicationParams $applicationParams
 * @var Yiisoft\Aliases\Aliases $aliases
 * @var Yiisoft\Assets\AssetManager $assetManager
 * @var CatalogSettings $catalogSettings
 * @var string $content
 * @var string|null $csrf
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 */

$assetManager->register(MainAsset::class);

$this->addCssFiles($assetManager->getCssFiles());
$this->addCssStrings($assetManager->getCssStrings());
$this->addJsFiles($assetManager->getJsFiles());
$this->addJsStrings($assetManager->getJsStrings());
$this->addJsVars($assetManager->getJsVars());

$routeName = (string) $currentRoute->getName();
$navItems = [
    [
        'label' => 'Prehľad',
        'icon' => 'ph-house',
        'url' => $urlGenerator->generate('home'),
        'active' => $routeName === 'home',
    ],
    [
        'label' => 'Katalóg',
        'icon' => 'ph-package',
        'url' => $urlGenerator->generate('catalog/list'),
        'active' => str_starts_with($routeName, 'catalog/'),
    ],
];

/** @var list<array{label: string, url?: string}> $breadcrumbs */
$breadcrumbs = $this->getParameter('breadcrumbs', []);
$isDemo = $catalogSettings->isDemo();

$this->beginPage()
?>
<!DOCTYPE html>
<html lang="<?= Html::encodeAttribute($applicationParams->locale) ?>" dir="ltr">
<head>
    <meta charset="<?= Html::encodeAttribute($applicationParams->charset) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= Html::encodeAttribute($aliases->get('@baseUrl/favicon.svg')) ?>" type="image/svg+xml">
    <title><?= Html::encode($this->getTitle()) ?> | <?= Html::encode($applicationParams->name) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<!-- Main navbar with horizontal navigation -->
<div class="navbar navbar-dark navbar-expand-xl navbar-static">
    <div class="container-fluid">
        <div class="d-flex d-xl-none me-2">
            <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbar-mobile"
                    aria-controls="navbar-mobile" aria-expanded="false" aria-label="Zobraziť navigáciu">
                <i class="ph-list" aria-hidden="true"></i>
            </button>
        </div>

        <div class="navbar-brand flex-1 flex-xl-0">
            <a href="<?= Html::encodeAttribute($urlGenerator->generate('home')) ?>" class="d-inline-flex align-items-center">
                <span class="h5 mb-0 text-white">eOil ERP</span>
            </a>
        </div>

        <div class="d-none d-xl-flex align-items-center order-xl-1 ms-auto">
            <span class="navbar-text text-white-50">Lokálny vývoj · bez prihlásenia</span>
        </div>

        <div class="navbar-collapse collapse" id="navbar-mobile">
            <ul class="navbar-nav mt-2 mt-xl-0">
                <?php foreach ($navItems as $item): ?>
                    <li class="nav-item">
                        <a href="<?= Html::encodeAttribute($item['url']) ?>"
                           class="navbar-nav-link rounded<?= $item['active'] ? ' active' : '' ?>"
                            <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                            <i class="<?= Html::encodeAttribute($item['icon']) ?>" aria-hidden="true"></i>
                            <span class="nav-label ms-2"><?= Html::encode($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<!-- /main navbar -->

<div class="page-content">
    <div class="content-wrapper">
        <div class="content-inner">

            <div class="page-header page-header-light shadow">
                <div class="page-header-content d-flex flex-wrap align-items-center gap-2 py-2">
                    <h4 class="page-title mb-0 py-1"><?= Html::encode($this->getTitle()) ?></h4>
                    <?php if ($isDemo): ?>
                        <span class="badge bg-warning text-dark" title="Syntetické údaje na vývoj, nie produkty z eOil">
                            <i class="ph-flask ph-sm me-1" aria-hidden="true"></i>Ukážkové údaje
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($breadcrumbs !== []): ?>
                    <div class="page-header-content border-top">
                        <nav class="breadcrumb py-2" aria-label="Omrvinková navigácia">
                            <a href="<?= Html::encodeAttribute($urlGenerator->generate('home')) ?>" class="breadcrumb-item"
                               aria-label="Prehľad">
                                <i class="ph-house" aria-hidden="true"></i>
                            </a>
                            <?php foreach ($breadcrumbs as $breadcrumb): ?>
                                <?php if (isset($breadcrumb['url'])): ?>
                                    <a href="<?= Html::encodeAttribute($breadcrumb['url']) ?>" class="breadcrumb-item">
                                        <?= Html::encode($breadcrumb['label']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="breadcrumb-item active" aria-current="page">
                                        <?= Html::encode($breadcrumb['label']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

            <main class="content pt-2">
                <?= $content ?>
            </main>

            <div class="navbar navbar-sm navbar-footer border-top">
                <div class="container-fluid">
                    <span class="text-muted">eOil ERP · lokálna vývojová verzia</span>
                </div>
            </div>

        </div>
    </div>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
