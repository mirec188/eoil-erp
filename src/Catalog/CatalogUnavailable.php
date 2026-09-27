<?php

declare(strict_types=1);

namespace App\Catalog;

use RuntimeException;

use function sprintf;

/**
 * The catalog source failed (transport, HTTP status, invalid response).
 *
 * Messages are composed only from our own text and the status code: never the token, headers,
 * raw response body or the underlying client exception (which is intentionally not chained).
 */
final class CatalogUnavailable extends RuntimeException
{
    public static function transport(): self
    {
        return new self('Catalog API request failed (transport error or timeout).');
    }

    public static function httpStatus(int $status): self
    {
        return new self(sprintf('Catalog API returned unexpected HTTP status %d.', $status));
    }

    public static function invalidResponse(string $reason): self
    {
        return new self(sprintf('Catalog API returned an invalid response: %s.', $reason));
    }
}
