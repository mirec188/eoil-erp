<?php

declare(strict_types=1);

namespace App\Shared\Http;

use RuntimeException;

/**
 * Upstream JSON could not be read or decoded. The message is one of a few fixed reasons.
 */
final class InvalidJsonResponse extends RuntimeException {}
