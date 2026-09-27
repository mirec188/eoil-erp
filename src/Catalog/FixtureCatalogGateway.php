<?php

declare(strict_types=1);

namespace App\Catalog;

use RuntimeException;
use Transliterator;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function in_array;
use function sprintf;
use function str_contains;
use function usort;

/**
 * Synthetic demo catalog for local development and tests.
 *
 * All names are visibly marked "Ukážk…" and none come from eOil, MRP or customer exports.
 * It covers: two pages (32 items), an inactive pack, a pack without unit and MRP reference,
 * an HTML-like name, diacritics, several MRP numbers per pack and MRP strings with
 * significant decimals/leading zeros ("901.01", "907.10", "0904.01").
 *
 * Search semantics (proposal for the M2 contract): case- and diacritics-insensitive substring
 * match on the name, or exact match on any MRP number.
 */
final class FixtureCatalogGateway implements CatalogGateway
{
    /** @var list<ProductPackView>|null */
    private ?array $items = null;
    private ?Transliterator $folder = null;

    public function search(CatalogQuery $query): CatalogPage
    {
        $items = $this->items();

        if ($query->term !== '') {
            $needle = $this->fold($query->term);
            $items = array_values(array_filter(
                $items,
                fn(ProductPackView $item): bool => in_array($query->term, $item->mrpNumbers, true)
                    || str_contains($this->fold($item->name), $needle),
            ));
        }

        return new CatalogPage(
            array_slice($items, $query->offset(), $query->pageSize),
            count($items),
            $query->page,
            $query->pageSize,
        );
    }

    public function get(int $id): ?ProductPackView
    {
        if ($id <= 0) {
            throw new InvalidCatalogQuery('ProductHasPack ID must be a positive integer.');
        }

        foreach ($this->items() as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<ProductPackView>
     */
    private function items(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $items = [
            new ProductPackView(101, 'Ukážkový motorový olej 5W-30', '1 l', 'l', true, ['901.01']),
            new ProductPackView(102, 'Ukážkový motorový olej 5W-30', '4 l', 'l', true, ['901.02']),
            new ProductPackView(103, 'Ukážkový prevodový olej 75W-90', '1 l', 'l', true, ['902.01']),
            new ProductPackView(104, 'Ukážkový prevodový olej 75W-90', '20 l', 'l', true, ['902.02', '902.20']),
            new ProductPackView(105, 'Ukážková hydraulická kvapalina HV 46', '20 l', 'l', true, ['903']),
            new ProductPackView(106, 'Ukážkové mazivo na ložiská', '400 g', 'kg', true, ['0904.01']),
            new ProductPackView(107, 'Ukážková chladiaca kvapalina G12 (vyradená)', '5 l', 'l', false, ['905.01']),
            new ProductPackView(108, 'Ukážková sada filtrov bez jednotky a MRP', '1 sada', null, true, []),
            new ProductPackView(109, 'Ukážka <img src=x onerror=alert(1)> & "HTML" v názve', '1 ks', 'ks', true, ['906.01']),
            new ProductPackView(110, 'Ukážkový čistič ťažkých nečistôt – ľahký', '750 ml', 'l', true, ['907.10']),
        ];

        $viscosities = [32, 46, 68, 100, 150, 220];
        $packs = ['1 l', '5 l', '20 l', '208 l'];
        for ($id = 111; $id <= 132; $id++) {
            $n = $id - 111;
            $items[] = new ProductPackView(
                $id,
                sprintf('Ukážkový priemyselný olej ISO VG %d', $viscosities[$n % count($viscosities)]),
                $packs[$n % count($packs)],
                'l',
                true,
                [sprintf('%d.01', 800 + $id - 110)],
            );
        }

        usort($items, static fn(ProductPackView $a, ProductPackView $b): int => $a->id <=> $b->id);

        return $this->items = $items;
    }

    private function fold(string $text): string
    {
        $this->folder ??= Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower()')
            ?? throw new RuntimeException('ICU transliterator is not available.');

        $folded = $this->folder->transliterate($text);
        if ($folded === false) {
            throw new RuntimeException('Unable to normalize search text.');
        }

        return $folded;
    }
}
