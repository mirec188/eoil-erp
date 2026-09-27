<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Yiisoft\Session\SessionInterface;

use function array_key_exists;
use function bin2hex;
use function random_bytes;

/**
 * SessionInterface in memory for unit tests; records ID regenerations.
 */
final class InMemorySession implements SessionInterface
{
    private array $data = [];
    private ?string $id = null;
    public int $regenerations = 0;

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->id ??= bin2hex(random_bytes(8));
        $this->data[$key] = $value;
    }

    public function close(): void {}

    public function open(): void
    {
        $this->id ??= bin2hex(random_bytes(8));
    }

    public function isActive(): bool
    {
        return $this->id !== null;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $sessionId): void
    {
        $this->id = $sessionId;
    }

    public function regenerateId(): void
    {
        $this->id = bin2hex(random_bytes(8));
        $this->regenerations++;
    }

    public function discard(): void {}

    public function getName(): string
    {
        return 'ERPSESSID';
    }

    public function all(): array
    {
        return $this->data;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function pull(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;
        unset($this->data[$key]);
        return $value;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->id = null;
    }

    public function getCookieParameters(): array
    {
        return [];
    }
}
