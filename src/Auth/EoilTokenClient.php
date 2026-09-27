<?php

declare(strict_types=1);

namespace App\Auth;

use App\Shared\Http\InvalidJsonResponse;
use App\Shared\Http\JsonResponseReader;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function array_is_list;
use function base64_encode;
use function http_build_query;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function strtolower;
use function time;
use function urlencode;

/**
 * Server-to-server code exchange with eOil: POST form, HTTP Basic client authentication (RFC 6749 §2.3.1),
 * finite timeout, no retries, no redirects, bounded response body.
 */
final class EoilTokenClient
{
    /** Tokens are treated as expired this many seconds early to absorb clock skew and request time. */
    private const EXPIRY_MARGIN_SECONDS = 15;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly EoilSignInSettings $settings,
    ) {}

    /**
     * @throws SignInFailed
     */
    public function exchange(string $code, string $codeVerifier, ?int $now = null): TokenGrant
    {
        $body = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $this->settings->redirectUri,
        ], '', '&');
        $credentials = urlencode($this->settings->clientId) . ':' . urlencode($this->settings->clientSecret());

        $request = $this->requestFactory
            ->createRequest('POST', $this->settings->tokenUrl)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Authorization', 'Basic ' . base64_encode($credentials))
            ->withHeader('User-Agent', 'eoil-erp')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface) {
            // The client exception may carry the request with the Authorization header; drop it.
            throw SignInFailed::unavailable('transport error or timeout');
        }

        $status = $response->getStatusCode();
        try {
            $data = JsonResponseReader::decode($response);
        } catch (InvalidJsonResponse $e) {
            throw SignInFailed::unavailable('HTTP ' . $status . ', ' . $e->getMessage());
        }
        $error = is_array($data) && is_string($data['error'] ?? null) ? $data['error'] : '';

        if ($status === 403 && $error === 'access_denied') {
            throw SignInFailed::denied();
        }
        if ($status === 400 && ($error === 'invalid_grant' || $error === 'invalid_request')) {
            throw SignInFailed::rejected($error);
        }
        if ($status !== 200) {
            // Includes 401 invalid_client: a configuration problem, not something the user can fix.
            throw SignInFailed::unavailable('HTTP ' . $status);
        }

        return $this->grant($data, $now ?? time());
    }

    private function grant(mixed $data, int $now): TokenGrant
    {
        if (!is_array($data) || array_is_list($data)) {
            throw SignInFailed::unavailable('token response is not an object');
        }
        $token = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;
        $user = $data['user'] ?? null;

        if (
            strtolower((string) ($data['token_type'] ?? '')) !== 'bearer'
            || !is_string($token)
            || preg_match('/^[A-Za-z0-9._~+\/=-]{16,4096}$/', $token) !== 1
            || !is_int($expiresIn)
            || $expiresIn < 30
            || $expiresIn > 3600
            || !is_array($user)
        ) {
            throw SignInFailed::unavailable('token response has unexpected fields');
        }

        $userId = $user['id'] ?? null;
        $displayName = $user['displayName'] ?? null;
        $roles = $user['roles'] ?? null;
        try {
            $signedIn = new SignedInUser(
                is_int($userId) ? $userId : 0,
                is_string($displayName) ? $displayName : '',
                is_array($roles) ? $roles : [],
            );
        } catch (InvalidArgumentException) {
            throw SignInFailed::unavailable('token response has an invalid user');
        }

        return new TokenGrant($token, $now + $expiresIn - self::EXPIRY_MARGIN_SECONDS, $signedIn);
    }
}
