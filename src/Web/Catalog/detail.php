<?php

declare(strict_types=1);

use App\Catalog\ProductPackView;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var ProductPackView $view
 * @var string $backUrl
 * @var list<array{label: string, url?: string}> $breadcrumbs
 */

$this->setTitle('Detail balenia');
$this->setParameter('breadcrumbs', [...$breadcrumbs, ['label' => $view->name]]);
?>

<?= $this->render(__DIR__ . '/_source-notice.php') ?>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h5 class="mb-0 catalog-name"><?= Html::encode($view->name) ?></h5>
        <?php if ($view->active): ?>
            <span class="badge bg-success">Aktívny</span>
        <?php else: ?>
            <span class="badge bg-dark bg-opacity-10 text-body">Neaktívny</span>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <dl class="row mb-0 catalog-detail">
            <dt class="col-sm-4 col-lg-3">ID balenia v eOil</dt>
            <dd class="col-sm-8 col-lg-9 font-monospace"><?= $view->id ?></dd>

            <dt class="col-sm-4 col-lg-3">Názov</dt>
            <dd class="col-sm-8 col-lg-9 catalog-name"><?= Html::encode($view->name) ?></dd>

            <dt class="col-sm-4 col-lg-3">Balenie</dt>
            <dd class="col-sm-8 col-lg-9"><?= Html::encode($view->packLabel) ?></dd>

            <dt class="col-sm-4 col-lg-3">Jednotka</dt>
            <dd class="col-sm-8 col-lg-9">
                <?php if ($view->unit === null): ?>
                    <span class="text-muted">neuvedená</span>
                <?php else: ?>
                    <?= Html::encode($view->unit) ?>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-4 col-lg-3">Stav</dt>
            <dd class="col-sm-8 col-lg-9"><?= $view->active ? 'Aktívny' : 'Neaktívny' ?></dd>

            <dt class="col-sm-4 col-lg-3">MRP čísla</dt>
            <dd class="col-sm-8 col-lg-9 mb-0">
                <?php if ($view->mrpNumbers === []): ?>
                    <span class="text-muted">Bez MRP referencie</span>
                <?php else: ?>
                    <?php foreach ($view->mrpNumbers as $mrpNumber): ?>
                        <span class="badge bg-light text-body font-monospace fs-sm"><?= Html::encode($mrpNumber) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </dd>
        </dl>
    </div>

    <div class="card-footer d-flex flex-wrap align-items-center gap-2">
        <a href="<?= Html::encodeAttribute($backUrl) ?>" class="btn btn-light">
            <i class="ph-arrow-left me-2" aria-hidden="true"></i>Späť na zoznam
        </a>
        <span class="text-muted ms-sm-auto">Len na čítanie — produkt a balenie spravuje eOil.</span>
    </div>
</div>
