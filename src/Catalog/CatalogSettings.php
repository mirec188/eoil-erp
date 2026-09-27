<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Environment;
use SensitiveParameter;

use function in_array;
use function is_numeric;
use function parse_url;
use function preg_match;
use function rtrim;
use function sprintf;
use function strtolower;

/**
 * Validated catalog source configuration.
 *
 * - `fixture` needs nothing else and is never allowed in production (see DevelopmentAccessPolicy).
 * - `http` requires an explicit base URL and token. There is no default URL of any real eOil system.
 *   Plain HTTP is accepted only in the test environment against a loopback mock server.
 */
final readonly class CatalogSettings
{
    public const DEFAULT_TIMEOUT = 5.0;
    public const MAX_TIMEOUT = 30.0;

    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '[::1]', '::1'];

    private function __construct(
        public CatalogSource $source,
        public ?string $apiBaseUrl,
        #[SensitiveParameter]
        private ?string $apiToken,
        public float $timeout,
    ) {}

    public static function fromEnvironment(
        string $appEnv,
        ?string $source,
        ?string $apiBaseUrl,
        #[SensitiveParameter]
        ?string $apiToken,
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
            return new self(CatalogSource::Fixture, null, null, $timeoutSeconds);
        }

        return new self(
            CatalogSource::Http,
            self::validateBaseUrl($appEnv, $apiBaseUrl),
            self::validateToken($apiToken),
            $timeoutSeconds,
        );
    }

    public function isDemo(): bool
    {
        return $this->source === CatalogSource::Fixture;
    }

    /**
     * @return non-empty-string
     */
    public function apiToken(): string
    {
        if ($this->apiToken === null || $this->apiToken === '') {
            throw new InvalidCatalogConfiguration('Catalog API token is not configured for this source.');
        }
        return $this->apiToken;
    }

    /**
     * Keeps the token out of var_dump()/debug output.
     */
    public function __debugInfo(): array
    {
        return [
            'source' => $this->source,
            'apiBaseUrl' => $this->apiBaseUrl,
            'apiToken' => $this->apiToken === null ? null : '***',
            'timeout' => $this->timeout,
        ];
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

    private static function validateBaseUrl(string $appEnv, ?string $url): string
    {
        if ($url === null) {
            throw new InvalidCatalogConfiguration('CATALOG_API_BASE_URL is required when CATALOG_SOURCE=http.');
        }

        $parts = parse_url($url);
        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidCatalogConfiguration(
                'CATALOG_API_BASE_URL must be an absolute URL without credentials, query or fragment.',
            );
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $plainHttpAllowed = $appEnv === Environment::TEST && in_array($host, self::LOOPBACK_HOSTS, true);

        if ($scheme !== 'https' && !($scheme === 'http' && $plainHttpAllowed)) {
            throw new InvalidCatalogConfiguration(
                'CATALOG_API_BASE_URL must use HTTPS. Plain HTTP is allowed only with APP_ENV=test and a loopback host.',
            );
        }

        return rtrim($url, '/');
    }

    private static function validateToken(#[SensitiveParameter] ?string $token): string
    {
        if ($token === null || preg_match('/^[\x21-\x7E]+$/', $token) !== 1) {
            throw new InvalidCatalogConfiguration(
                'CATALOG_API_TOKEN is required when CATALOG_SOURCE=http and must be printable ASCII without spaces.',
            );
        }
        return $token;
    }
}
