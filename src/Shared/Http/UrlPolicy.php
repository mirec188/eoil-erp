<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Environment;

use function in_array;
use function is_string;
use function parse_url;
use function strtolower;

/**
 * Which absolute URLs may be configured for eOil (API, token endpoint, authorize page, callback).
 *
 * HTTPS is always accepted. Plain HTTP only for local development: APP_ENV=test on loopback,
 * APP_ENV=dev on loopback or host.docker.internal (a MAMP eOil reached from the ERP container).
 * No credentials, query or fragment in configured base URLs.
 */
final class UrlPolicy
{
    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '[::1]', '::1'];
    private const DEV_HOSTS = ['127.0.0.1', 'localhost', '[::1]', '::1', 'host.docker.internal'];

    /**
     * @return array{scheme: string, host: string, path: string}|null parts, or null when not allowed
     */
    public static function check(string $appEnv, ?string $url): ?array
    {
        if (!is_string($url) || $url === '') {
            return null;
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
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $plainHttpAllowed = ($appEnv === Environment::TEST && in_array($host, self::LOOPBACK_HOSTS, true))
            || ($appEnv === Environment::DEV && in_array($host, self::DEV_HOSTS, true));

        if ($scheme !== 'https' && !($scheme === 'http' && $plainHttpAllowed)) {
            return null;
        }

        return ['scheme' => $scheme, 'host' => $host, 'path' => $parts['path'] ?? ''];
    }
}
