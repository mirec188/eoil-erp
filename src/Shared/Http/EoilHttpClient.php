<?php

declare(strict_types=1);

namespace App\Shared\Http;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientInterface;

use function min;

/**
 * PSR-18 client for eOil calls: finite timeouts, no retries, redirects refused (a bearer token or
 * client secret is never forwarded elsewhere), streamed bodies so JsonResponseReader bounds memory.
 */
final class EoilHttpClient
{
    public static function create(float $timeout): ClientInterface
    {
        return new Client([
            RequestOptions::TIMEOUT => $timeout,
            RequestOptions::CONNECT_TIMEOUT => min($timeout, 3.0),
            RequestOptions::READ_TIMEOUT => $timeout,
            RequestOptions::STREAM => true,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
        ]);
    }
}
