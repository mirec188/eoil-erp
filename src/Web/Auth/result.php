<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $heading
 * @var string $message
 */

$this->setTitle($heading);
?>

<div class="card">
    <div class="card-body">
        <h5><?= Html::encode($heading) ?></h5>
        <p><?= Html::encode($message) ?></p>
        <a href="/login" class="btn btn-light">
            <i class="ph-arrow-left me-2" aria-hidden="true"></i>Späť na prihlásenie
        </a>
    </div>
</div>
