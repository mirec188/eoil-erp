<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 */

$this->setTitle('Stránka neexistuje');
?>

<div class="card">
    <div class="card-body">
        <h5>404 — stránka neexistuje</h5>
        <p>
            Adresa <code><?= Html::encode($currentRoute->getUri()?->getPath() ?? 'neznáma') ?></code>
            v eOil ERP neexistuje.
        </p>
        <a href="<?= Html::encodeAttribute($urlGenerator->generate('home')) ?>" class="btn btn-light">
            <i class="ph-arrow-left me-2" aria-hidden="true"></i>Späť na prehľad
        </a>
    </div>
</div>
