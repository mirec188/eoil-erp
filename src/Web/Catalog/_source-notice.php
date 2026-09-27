<?php

declare(strict_types=1);

/**
 * States where catalog data comes from. Shown on every catalog page.
 *
 * @var App\Catalog\CatalogSettings $catalogSettings
 */
?>
<?php if ($catalogSettings->isDemo()): ?>
    <div class="alert alert-warning border-warning d-flex align-items-start gap-2 py-2" role="note">
        <i class="ph-flask flex-shrink-0 mt-1" aria-hidden="true"></i>
        <div>
            <strong>Ukážkové údaje.</strong>
            Tieto produkty sú vymyslené na skúšanie. Nie sú to skutočné produkty z eOil.
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info d-flex align-items-start gap-2 py-2" role="note">
        <i class="ph-plugs-connected flex-shrink-0 mt-1" aria-hidden="true"></i>
        <div>
            <strong>Testovacie napojenie.</strong>
            Údaje pochádzajú z nastaveného testovacieho zdroja. Živé napojenie na eOil nie je overené.
        </div>
    </div>
<?php endif; ?>
