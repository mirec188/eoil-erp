<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

use function in_array;
use function sprintf;

final class Environment
{
    public const DEV = 'dev';
    public const TEST = 'test';
    public const PROD = 'prod';

    public const ENVIRONMENTS = [
        self::DEV,
        self::TEST,
        self::PROD,
    ];

    private static array $values = [];

    public static function prepare(): void
    {
        self::setEnvironment();
        self::setBoolean('APP_C3', false);
        self::setBoolean('APP_DEBUG', false);
        self::setNonEmptyStringOrNull('APP_HOST_PATH', null);
        self::setNonEmptyStringOrNull('CATALOG_SOURCE', null);
        self::setNonEmptyStringOrNull('CATALOG_API_TIMEOUT', null);
        self::setNonEmptyStringOrNull('EOIL_API_BASE_URL', null);
        self::setNonEmptyStringOrNull('EOIL_AUTHORIZE_URL', null);
        self::setNonEmptyStringOrNull('EOIL_CLIENT_ID', null);
        self::setNonEmptyStringOrNull('EOIL_CLIENT_SECRET', null);
        self::setNonEmptyStringOrNull('ERP_REDIRECT_URI', null);
    }

    /**
     * Explicit catalog source: "fixture" (synthetic demo data) or "http" (eOil API adapter).
     * There is intentionally no default.
     */
    public static function catalogSource(): ?string
    {
        /** @var string|null */
        return self::$values['CATALOG_SOURCE'];
    }

    public static function catalogApiTimeout(): ?string
    {
        /** @var string|null */
        return self::$values['CATALOG_API_TIMEOUT'];
    }

    /** Server-side base URL of the eOil backend (catalog API and token endpoint). */
    public static function eoilApiBaseUrl(): ?string
    {
        /** @var string|null */
        return self::$values['EOIL_API_BASE_URL'];
    }

    /** Browser-facing URL of the eOil sign-in (authorize) page. */
    public static function eoilAuthorizeUrl(): ?string
    {
        /** @var string|null */
        return self::$values['EOIL_AUTHORIZE_URL'];
    }

    public static function eoilClientId(): ?string
    {
        /** @var string|null */
        return self::$values['EOIL_CLIENT_ID'];
    }

    public static function eoilClientSecret(): ?string
    {
        /** @var string|null */
        return self::$values['EOIL_CLIENT_SECRET'];
    }

    /** Absolute callback URL of this ERP, registered in eOil. */
    public static function erpRedirectUri(): ?string
    {
        /** @var string|null */
        return self::$values['ERP_REDIRECT_URI'];
    }

    /**
     * @return non-empty-string
     */
    public static function appEnv(): string
    {
        /** @var non-empty-string */
        return self::$values['APP_ENV'];
    }

    public static function isDev(): bool
    {
        return self::appEnv() === self::DEV;
    }

    public static function isTest(): bool
    {
        return self::appEnv() === self::TEST;
    }

    public static function isProd(): bool
    {
        return self::appEnv() === self::PROD;
    }

    /**
     * @return non-empty-string|null
     */
    public static function appHostPath(): ?string
    {
        /** @var non-empty-string|null */
        return self::$values['APP_HOST_PATH'];
    }

    public static function appC3(): bool
    {
        /** @var bool */
        return self::$values['APP_C3'];
    }

    public static function appDebug(): bool
    {
        /** @var bool */
        return self::$values['APP_DEBUG'];
    }

    private static function setEnvironment(): void
    {
        $environment = self::getRawValue('APP_ENV') ?: self::PROD;

        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new RuntimeException(
                sprintf(
                    'APP_ENV="%s" is invalid. Valid values are "%s".',
                    $environment,
                    implode('", "', self::ENVIRONMENTS),
                ),
            );
        }

        self::$values['APP_ENV'] = $environment;
    }

    private static function setBoolean(string $key, bool $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value === null
            ? $default
            : (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default);
    }

    private static function setInteger(string $key, int $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value === null ? $default : (int) $value;
    }

    private static function setString(string $key, string $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value ?? $default;
    }

    private static function setNonEmptyStringOrNull(string $key, ?string $default): void
    {
        $value = self::getRawValue($key);
        self::$values[$key] = $value === null || $value === '' ? $default : $value;
    }

    private static function getRawValue(string $key): ?string
    {
        $value = getenv($key, true);
        if ($value !== false) {
            return $value;
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return isset($_ENV[$key]) ? (string) $_ENV[$key] : null;
    }
}
