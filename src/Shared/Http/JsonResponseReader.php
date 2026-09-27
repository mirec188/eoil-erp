<?php

declare(strict_types=1);

namespace App\Shared\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function json_decode;
use function min;
use function str_contains;
use function strlen;
use function strtolower;

/**
 * Bounded, sanitized JSON decoding of an upstream response (catalog API, token endpoint).
 *
 * Reads at most MAX_BODY_BYTES + 1 bytes, so an oversized or endless body never fills memory
 * (the client streams the body). Every failure is an InvalidJsonResponse with a fixed reason;
 * stream and parser messages are never propagated.
 */
final class JsonResponseReader
{
    public const MAX_BODY_BYTES = 2_000_000;
    private const READ_CHUNK_BYTES = 65_536;
    private const JSON_DEPTH = 16;

    /**
     * @throws InvalidJsonResponse
     */
    public static function decode(ResponseInterface $response): mixed
    {
        if (!str_contains(strtolower($response->getHeaderLine('Content-Type')), 'application/json')) {
            throw new InvalidJsonResponse('content type is not application/json');
        }

        try {
            return json_decode(self::readBody($response), true, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidJsonResponse('malformed JSON');
        }
    }

    private static function readBody(ResponseInterface $response): string
    {
        $body = '';
        $size = null;
        try {
            $stream = $response->getBody();
            $size = $stream->getSize();
            if ($size === null || $size <= self::MAX_BODY_BYTES) {
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                while (strlen($body) <= self::MAX_BODY_BYTES && !$stream->eof()) {
                    $chunk = $stream->read(min(self::READ_CHUNK_BYTES, self::MAX_BODY_BYTES + 1 - strlen($body)));
                    if ($chunk === '') {
                        break;
                    }
                    $body .= $chunk;
                }
            }
        } catch (RuntimeException) {
            throw new InvalidJsonResponse('body could not be read');
        }

        if (($size ?? 0) > self::MAX_BODY_BYTES || strlen($body) > self::MAX_BODY_BYTES) {
            throw new InvalidJsonResponse('body is too large');
        }

        return $body;
    }
}
