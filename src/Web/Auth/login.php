<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string|null $notice
 * @var string $startUrl
 */

$this->setTitle('Prihlásenie');

$notices = [
    'expired' => ['alert-warning', 'Prihlásenie vypršalo. Prihláste sa znova.'],
    'denied' => ['alert-danger', 'Prístup bol zamietnutý.'],
    'signed-out' => ['alert-info', 'Boli ste odhlásený z ERP. Prihlásenie v eOil zostáva platné.'],
];
?>

<?php if ($notice !== null && isset($notices[$notice])): ?>
    <div class="alert <?= Html::encodeAttribute($notices[$notice][0]) ?> py-2" role="status">
        <?= Html::encode($notices[$notice][1]) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h5>Prihlásenie do eOil ERP</h5>
        <p class="mb-3">
            Do ERP sa prihlasujete svojím účtom eOil. Heslo zadávate iba v eOil, ERP ho nevidí.
        </p>
        <a href="<?= Html::encodeAttribute($startUrl) ?>" class="btn btn-primary">
            <i class="ph-sign-in me-2" aria-hidden="true"></i>Prihlásiť sa cez eOil
        </a>
    </div>
</div>
