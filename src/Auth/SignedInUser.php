<?php

declare(strict_types=1);

namespace App\Auth;

use InvalidArgumentException;

use function array_is_list;
use function is_string;
use function mb_strlen;
use function trim;

/**
 * The eOil user this ERP session acts for. eOil is the authority; the ERP keeps only a reference.
 */
final readonly class SignedInUser
{
    /** @var list<string> eOil roles that grant ERP access */
    public array $roles;

    /**
     * @param array<array-key, mixed> $roles validated here: non-empty list of non-empty strings
     */
    public function __construct(
        public int $id,
        public string $displayName,
        array $roles,
    ) {
        if ($id <= 0) {
            throw new InvalidArgumentException('eOil user ID must be positive.');
        }
        if (trim($displayName) === '' || mb_strlen($displayName) > 200) {
            throw new InvalidArgumentException('Display name must be non-empty text of at most 200 characters.');
        }
        if ($roles === [] || !array_is_list($roles)) {
            throw new InvalidArgumentException('At least one ERP role is required.');
        }
        foreach ($roles as $role) {
            if (!is_string($role) || $role === '' || mb_strlen($role) > 64) {
                throw new InvalidArgumentException('Roles must be non-empty strings.');
            }
        }
        /** @var list<string> $roles */
        $this->roles = $roles;
    }
}
