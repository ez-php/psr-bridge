<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Http\RequestInterface;
use EzPhp\Http\UploadedFile;

/**
 * A RequestInterface implementation other than ez-php/http's Request, for the
 * converter's rejection path. Hand-written because PHPUnit cannot double an
 * interface with a method named "method".
 *
 * @package Tests\Support
 */
final class PsrBridgeForeignRequest implements RequestInterface
{
    public function method(): string
    {
        return 'GET';
    }

    public function uri(): string
    {
        return '/';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [];
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function hasQuery(string $key): bool
    {
        return false;
    }

    public function hasInput(string $key): bool
    {
        return false;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function rawBody(): string
    {
        return '';
    }

    /**
     * @param list<string> $trustedProxies
     */
    public function ip(array $trustedProxies = []): string
    {
        return '';
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function file(string $key): ?UploadedFile
    {
        return null;
    }

    public function contentType(): ?string
    {
        return null;
    }

    public function isJson(): bool
    {
        return false;
    }

    public function acceptsJson(): bool
    {
        return false;
    }

    public function wantsJson(): bool
    {
        return false;
    }

    public function accepts(string $contentType): bool
    {
        return false;
    }

    public function withAttribute(string $name, mixed $value): static
    {
        return $this;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $default;
    }
}
