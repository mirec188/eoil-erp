<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * Catalog source failed. Deliberately shows no upstream details (no raw response, status or token).
 *
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var string $retryUrl
 * @var list<array{label: string, url?: string}> $breadcrumbs
 */

$this->setTitle('Katalóg');
if ($breadcrumbs !== []) {
    $this->setParameter('breadcrumbs', [...$breadcrumbs, ['label' => 'Chyba načítania']]);
}
?>

<?= $this->render(__DIR__ . '/_source-notice.php') ?>

<div class="card border-danger">
    <div class="card-body">
        <div class="d-flex align-items-start gap-3">
            <i class="ph-warning-circle ph-2x text-danger flex-shrink-0" aria-hidden="true"></i>
            <div role="alert">
                <h5 class="text-danger">Katalóg sa nepodarilo načítať</h5>
                <p>
                    Zdroj katalógu neodpovedal včas alebo vrátil neplatnú odpoveď.
                    Nejde o prázdny katalóg — údaje sa nezobrazia, kým sa načítanie nepodarí.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= Html::encodeAttribute($retryUrl) ?>" class="btn btn-danger">
                        <i class="ph-arrow-clockwise me-2" aria-hidden="true"></i>Skúsiť znova
                    </a>
                    <a href="<?= Html::encodeAttribute($urlGenerator->generate('home')) ?>" class="btn btn-light">Prehľad</a>
                </div>
            </div>
        </div>
    </div>
</div>
