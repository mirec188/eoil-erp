<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Shared\Http\UrlPolicy;

use function is_numeric;
use function rtrim;
use function sprintf;

/**
 * Validated catalog source configuration.
 *
 * - `fixture` needs nothing else and is never allowed in production (see DevelopmentAccessPolicy).
 * - `http` reads the eOil API with the access token of the signed-in user and therefore requires
 *   eOil sign-in (App\Auth\EoilSignInSettings). It needs an explicit eOil base URL; there is no default
 *   URL of any real eOil system. Plain HTTP only for local development (App\Shared\Http\UrlPolicy).
 */
final readonly class CatalogSettings
{
    public const DEFAULT_TIMEOUT = 5.0;
    public const MAX_TIMEOUT = 30.0;

    private function __construct(
        public CatalogSource $source,
        public ?string $apiBaseUrl,
        public float $timeout,
    ) {}

    public static function fromEnvironment(
        string $appEnv,
        ?string $source,
        ?string $apiBaseUrl,
        ?string $timeout,
    ): self {
        $catalogSource = $source === null ? null : CatalogSource::tryFrom($source);
        if ($catalogSource === null) {
            throw new InvalidCatalogConfiguration(
                'CATALOG_SOURCE must be set explicitly to "fixture" or "http".',
            );
        }

        $timeoutSeconds = self::parseTimeout($timeout);

        if ($catalogSource === CatalogSource::Fixture) {
            return new self(CatalogSource::Fixture, null, $timeoutSeconds);
        }

        if (UrlPolicy::check($appEnv, $apiBaseUrl) === null) {
            throw new InvalidCatalogConfiguration(
                'EOIL_API_BASE_URL is required when CATALOG_SOURCE=http: an absolute HTTPS URL without credentials, '
                . 'query or fragment (plain HTTP only for local development hosts).',
            );
        }

        return new self(CatalogSource::Http, rtrim((string) $apiBaseUrl, '/'), $timeoutSeconds);
    }

    public function isDemo(): bool
    {
        return $this->source === CatalogSource::Fixture;
    }

    /** Real eOil data is only read for a signed-in eOil user. */
    public function requiresSignIn(): bool
    {
        return $this->source === CatalogSource::Http;
    }

    private static function parseTimeout(?string $timeout): float
    {
        if ($timeout === null) {
            return self::DEFAULT_TIMEOUT;
        }
        if (!is_numeric($timeout) || (float) $timeout <= 0 || (float) $timeout > self::MAX_TIMEOUT) {
            throw new InvalidCatalogConfiguration(
                sprintf('CATALOG_API_TIMEOUT must be a number of seconds in (0, %d].', (int) self::MAX_TIMEOUT),
            );
        }
        return (float) $timeout;
    }
}
