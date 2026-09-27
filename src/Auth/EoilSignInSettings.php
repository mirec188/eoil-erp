<?php

declare(strict_types=1);

namespace App\Auth;

use App\Catalog\CatalogSettings;
use App\Catalog\InvalidCatalogConfiguration;
use App\Shared\Http\UrlPolicy;
use SensitiveParameter;

use function preg_match;
use function strlen;

/**
 * Sign-in through eOil (OAuth 2.0 authorization code + PKCE, confidential client).
 * Enabled exactly when the catalog reads real eOil data (CATALOG_SOURCE=http).
 */
final readonly class EoilSignInSettings
{
    public const CALLBACK_PATH = '/auth/callback';
    public const TOKEN_PATH = '/erp-api/v1/auth/token';
    private const MIN_SECRET_LENGTH = 32;

    private function __construct(
        public bool $enabled,
        public string $authorizeUrl,
        public string $tokenUrl,
        public string $clientId,
        #[SensitiveParameter]
        private string $clientSecret,
        public string $redirectUri,
        public float $timeout,
    ) {}

    public static function disabled(): self
    {
        return new self(false, '', '', '', '', '', CatalogSettings::DEFAULT_TIMEOUT);
    }

    public static function fromEnvironment(
        string $appEnv,
        CatalogSettings $catalog,
        ?string $authorizeUrl,
        ?string $clientId,
        #[SensitiveParameter]
        ?string $clientSecret,
        ?string $redirectUri,
    ): self {
        if (!$catalog->requiresSignIn()) {
            return self::disabled();
        }

        if (UrlPolicy::check($appEnv, $authorizeUrl) === null) {
            throw new InvalidCatalogConfiguration('EOIL_AUTHORIZE_URL must be an absolute URL of the eOil sign-in page.');
        }
        $callback = UrlPolicy::check($appEnv, $redirectUri);
        if ($callback === null || $callback['path'] !== self::CALLBACK_PATH) {
            throw new InvalidCatalogConfiguration('ERP_REDIRECT_URI must be the absolute URL of this ERP ending in /auth/callback.');
        }
        $clientId ??= 'eoil-erp';
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $clientId) !== 1) {
            throw new InvalidCatalogConfiguration('EOIL_CLIENT_ID has an invalid format.');
        }
        if ($clientSecret === null || strlen($clientSecret) < self::MIN_SECRET_LENGTH || preg_match('/^[\x21-\x7E]+$/', $clientSecret) !== 1) {
            throw new InvalidCatalogConfiguration('EOIL_CLIENT_SECRET is required: at least 32 printable ASCII characters without spaces.');
        }

        return new self(
            true,
            (string) $authorizeUrl,
            (string) $catalog->apiBaseUrl . self::TOKEN_PATH,
            $clientId,
            $clientSecret,
            (string) $redirectUri,
            $catalog->timeout,
        );
    }

    public function clientSecret(): string
    {
        return $this->clientSecret;
    }

    public function __debugInfo(): array
    {
        return [
            'enabled' => $this->enabled,
            'authorizeUrl' => $this->authorizeUrl,
            'tokenUrl' => $this->tokenUrl,
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret === '' ? '' : '***',
            'redirectUri' => $this->redirectUri,
            'timeout' => $this->timeout,
        ];
    }
}
