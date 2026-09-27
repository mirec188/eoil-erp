<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * HTTP 400 for invalid catalog input. The rejected value itself is not echoed.
 *
 * @var Yiisoft\View\WebView $this
 * @var string $heading
 * @var string $message
 * @var string $backLabel
 * @var string $backUrl
 */

$this->setTitle('Katalóg');
?>

<?= $this->render(__DIR__ . '/_source-notice.php') ?>

<div class="card border-warning">
    <div class="card-body">
        <h5><?= Html::encode($heading) ?></h5>
        <p><?= Html::encode($message) ?></p>
        <a href="<?= Html::encodeAttribute($backUrl) ?>" class="btn btn-light">
            <i class="ph-arrow-left me-2" aria-hidden="true"></i><?= Html::encode($backLabel) ?>
        </a>
    </div>
</div>
