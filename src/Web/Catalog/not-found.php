<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var int $id
 * @var string $backUrl
 * @var list<array{label: string, url?: string}> $breadcrumbs
 */

$this->setTitle('Detail balenia');
$this->setParameter('breadcrumbs', [...$breadcrumbs, ['label' => 'Nenájdené']]);
?>

<?= $this->render(__DIR__ . '/_source-notice.php') ?>

<div class="card">
    <div class="card-body">
        <h5>Produkt sa nenašiel</h5>
        <p>Balenie s ID <span class="font-monospace"><?= $id ?></span> v katalógu neexistuje.</p>
        <a href="<?= Html::encodeAttribute($backUrl) ?>" class="btn btn-light">
            <i class="ph-arrow-left me-2" aria-hidden="true"></i>Späť na zoznam
        </a>
    </div>
</div>
