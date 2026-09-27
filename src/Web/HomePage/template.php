<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * Business-facing overview. Implementation status and milestones live in docs, not in the UI.
 *
 * @var Yiisoft\View\WebView $this
 * @var App\Catalog\CatalogSettings $catalogSettings
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 */

$this->setTitle('Prehľad');
?>

<div class="card">
    <div class="card-body">
        <h5>Vitajte v eOil ERP</h5>
        <p class="mb-3">
            Katalóg produktov a balení z eOil na jednom mieste: vyhľadávanie podľa názvu alebo MRP čísla
            a detail každého balenia.
        </p>
        <?php if ($catalogSettings->isDemo()): ?>
            <p class="mb-3 text-muted">
                Zatiaľ pracujete s ukážkovými údajmi. Nie sú to skutočné produkty z eOil.
            </p>
        <?php endif; ?>
        <a href="<?= Html::encodeAttribute($urlGenerator->generate('catalog/list')) ?>" class="btn btn-primary">
            <i class="ph-package me-2" aria-hidden="true"></i>Otvoriť katalóg
        </a>
    </div>
</div>
