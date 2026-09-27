<?php

declare(strict_types=1);

namespace App\Catalog;

use InvalidArgumentException;

use function array_is_list;
use function is_string;
use function mb_strlen;
use function trim;

/**
 * Read-only view of an eOil ProductHasPack. eOil is the authority; the ERP never creates or edits it.
 */
final readonly class ProductPackView
{
    /**
     * MRP references as unaltered strings (e.g. "901.01" keeps its ".01"; "0904.01" keeps its leading zero).
     *
     * @var list<string>
     */
    public array $mrpNumbers;

    /**
     * @param int $id Positive eOil ProductHasPack ID.
     * @param array<array-key, mixed> $mrpNumbers Validated here: must be a list of non-empty unpadded strings.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $packLabel,
        public ?string $unit,
        public bool $active,
        array $mrpNumbers,
    ) {
        if ($id <= 0) {
            throw new InvalidArgumentException('ProductHasPack ID must be a positive integer.');
        }
        self::assertText($name, 'name', 500);
        self::assertText($packLabel, 'packLabel', 100);
        if ($unit !== null) {
            // eOil Entity names reach 29 characters (measured in eoil_test).
            self::assertText($unit, 'unit', 64);
        }
        if (!array_is_list($mrpNumbers)) {
            throw new InvalidArgumentException('mrpNumbers must be a list.');
        }
        foreach ($mrpNumbers as $mrpNumber) {
            if (!is_string($mrpNumber) || $mrpNumber === '' || trim($mrpNumber) !== $mrpNumber || mb_strlen($mrpNumber) > 64) {
                throw new InvalidArgumentException('Each MRP number must be a non-empty unpadded string.');
            }
        }
        /** @var list<string> $mrpNumbers */
        $this->mrpNumbers = $mrpNumbers;
    }

    private static function assertText(string $value, string $field, int $maxLength): void
    {
        if (trim($value) === '' || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("$field must be non-empty text of at most $maxLength characters.");
        }
    }
}
