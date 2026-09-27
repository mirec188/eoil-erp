<?php

declare(strict_types=1);

use App\Catalog\CatalogPage;
use App\Catalog\CatalogQuery;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var CatalogQuery $query
 * @var CatalogPage $page
 * @var array{q?: string, page?: int} $listParameters
 */

$this->setTitle('Katalóg');

$returnParameters = $listParameters;
$pageUrl = static function (int $number) use ($urlGenerator, $query): string {
    $params = $query->term === '' ? [] : ['q' => $query->term];
    if ($number > 1) {
        $params['page'] = $number;
    }
    return $urlGenerator->generate('catalog/list', [], $params);
};

// Compact window of page numbers around the current page.
$pageCount = $page->pageCount();
$window = array_values(array_unique(array_filter(
    [1, $page->page - 1, $page->page, $page->page + 1, $pageCount],
    static fn(int $n): bool => $n >= 1 && $n <= $pageCount,
)));
sort($window);
$beyondRange = $page->isEmpty() && $page->total > 0;
?>

<?= $this->render(__DIR__ . '/_source-notice.php') ?>

<div class="card">
    <div class="card-body">
        <form method="get" action="<?= Html::encodeAttribute($urlGenerator->generate('catalog/list')) ?>" role="search">
            <label for="catalog-q" class="form-label">Hľadať podľa názvu alebo presného MRP čísla</label>
            <div class="input-group">
                <span class="input-group-text"><i class="ph-magnifying-glass" aria-hidden="true"></i></span>
                <input type="search" id="catalog-q" name="q" class="form-control"
                       value="<?= Html::encodeAttribute($query->term) ?>"
                       maxlength="<?= CatalogQuery::MAX_TERM_LENGTH ?>"
                       placeholder="napr. 901.01 alebo prevodový olej"
                       autocomplete="off" spellcheck="false">
                <button type="submit" class="btn btn-primary">Hľadať</button>
            </div>
        </form>
    </div>

    <div class="card-body border-top py-2 d-flex flex-wrap align-items-center gap-2 text-muted">
        <span><?= $query->term === '' ? 'Položiek' : 'Nájdené' ?>: <?= $page->total ?></span>
        <?php if ($query->term !== ''): ?>
            <span>·</span>
            <a href="<?= Html::encodeAttribute($urlGenerator->generate('catalog/list')) ?>">Zrušiť vyhľadávanie</a>
        <?php endif; ?>
    </div>

    <?php if ($page->total === 0): ?>
        <div class="card-body border-top text-center py-4">
            <i class="ph-magnifying-glass ph-2x text-muted mb-2" aria-hidden="true"></i>
            <?php if ($query->term !== ''): ?>
                <p class="mb-0">
                    Žiadny produkt nezodpovedá vyhľadávaniu „<?= Html::encode($query->term) ?>“.
                </p>
            <?php else: ?>
                <p class="mb-0">Katalóg neobsahuje žiadne položky.</p>
            <?php endif; ?>
        </div>
    <?php elseif ($beyondRange): ?>
        <div class="card-body border-top text-center py-4">
            <p class="mb-2">Na tejto strane nie sú žiadne položky.</p>
            <a href="<?= Html::encodeAttribute($pageUrl(1)) ?>" class="btn btn-light">Prejsť na prvú stranu</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover catalog-table mb-0">
                <thead>
                <tr>
                    <th scope="col" class="text-nowrap">ID balenia v eOil</th>
                    <th scope="col">Názov</th>
                    <th scope="col">Balenie</th>
                    <th scope="col">Jednotka</th>
                    <th scope="col">MRP</th>
                    <th scope="col">Stav</th>
                    <th scope="col"><span class="visually-hidden">Akcie</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($page->items as $item): ?>
                    <tr>
                        <td class="font-monospace" data-label="ID eOil"><?= $item->id ?></td>
                        <td class="catalog-name" data-label="Názov"><?= Html::encode($item->name) ?></td>
                        <td class="text-nowrap" data-label="Balenie"><?= Html::encode($item->packLabel) ?></td>
                        <td data-label="Jednotka">
                            <?php if ($item->unit === null): ?>
                                <span class="text-muted" title="Jednotka nie je uvedená">—</span>
                            <?php else: ?>
                                <?= Html::encode($item->unit) ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="MRP">
                            <?php if ($item->mrpNumbers === []): ?>
                                <span class="text-muted" title="Bez MRP referencie">—</span>
                            <?php else: ?>
                                <?php foreach ($item->mrpNumbers as $mrpNumber): ?>
                                    <span class="badge bg-light text-body font-monospace"><?= Html::encode($mrpNumber) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Stav">
                            <?php if ($item->active): ?>
                                <span class="badge bg-success">Aktívny</span>
                            <?php else: ?>
                                <span class="badge bg-dark bg-opacity-10 text-body">Neaktívny</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end catalog-actions">
                            <a href="<?= Html::encodeAttribute($urlGenerator->generate('catalog/detail', ['id' => (string) $item->id], $returnParameters)) ?>"
                               class="btn btn-sm btn-light"
                               aria-label="<?= Html::encodeAttribute('Detail: ' . $item->name) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted me-auto">Strana <?= $page->page ?> z <?= $pageCount ?></span>
            <?php if ($pageCount > 1): ?>
                <nav aria-label="Stránkovanie katalógu">
                    <ul class="pagination pagination-sm mb-0 flex-wrap">
                        <li class="page-item<?= $page->hasPrevious() ? '' : ' disabled' ?>">
                            <?php if ($page->hasPrevious()): ?>
                                <a class="page-link" href="<?= Html::encodeAttribute($pageUrl($page->page - 1)) ?>" rel="prev">Predchádzajúca</a>
                            <?php else: ?>
                                <span class="page-link">Predchádzajúca</span>
                            <?php endif; ?>
                        </li>
                        <?php $previous = 0; ?>
                        <?php foreach ($window as $number): ?>
                            <?php if ($number - $previous > 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <?php if ($number === $page->page): ?>
                                <li class="page-item active" aria-current="page"><span class="page-link"><?= $number ?></span></li>
                            <?php else: ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= Html::encodeAttribute($pageUrl($number)) ?>"
                                       aria-label="Strana <?= $number ?>"><?= $number ?></a>
                                </li>
                            <?php endif; ?>
                            <?php $previous = $number; ?>
                        <?php endforeach; ?>
                        <li class="page-item<?= $page->hasNext() ? '' : ' disabled' ?>">
                            <?php if ($page->hasNext()): ?>
                                <a class="page-link" href="<?= Html::encodeAttribute($pageUrl($page->page + 1)) ?>" rel="next">Ďalšia</a>
                            <?php else: ?>
                                <span class="page-link">Ďalšia</span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
