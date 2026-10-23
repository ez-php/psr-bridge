<?php

declare(strict_types=1);

namespace EzPhp\PsrBridge\Http;

use InvalidArgumentException;

/**
 * Class SetCookieParser
 *
 * Parses one `Set-Cookie` header value into the named arguments of ez-php's
 * `Response::withCookie()` — the inverse of `Cookie::toHeaderValue()`
 * (URL-decoded name/value, Max-Age, Path, Domain, Secure, HttpOnly, SameSite).
 * `Expires` is converted to a TTL when no Max-Age is present.
 *
 * @package EzPhp\PsrBridge\Http
 */
final class SetCookieParser
{
    /**
     * @param string $header A single Set-Cookie value, e.g. "sid=abc; Path=/; HttpOnly".
     *
     * @throws InvalidArgumentException When the value has no `name=value` pair.
     *
     * @return array{name: string, value: string, ttl: int, path: string, domain: string, secure: bool, httpOnly: bool, sameSite: string}
     */
    public static function parse(string $header): array
    {
        $parts = array_map('trim', explode(';', $header));
        $pair = array_shift($parts);

        if (!str_contains($pair, '=') || str_starts_with($pair, '=')) {
            throw new InvalidArgumentException(sprintf('Invalid Set-Cookie value "%s".', $header));
        }

        [$name, $value] = explode('=', $pair, 2);

        $cookie = [
            'name' => urldecode($name),
            'value' => urldecode($value),
            'ttl' => 0,
            'path' => '',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
            'sameSite' => '',
        ];

        $maxAge = null;
        $expires = null;

        foreach ($parts as $part) {
            [$attribute, $attributeValue] = array_pad(explode('=', $part, 2), 2, '');

            match (strtolower($attribute)) {
                'max-age' => $maxAge = (int) $attributeValue,
                'expires' => $expires = strtotime($attributeValue),
                'path' => $cookie['path'] = $attributeValue,
                'domain' => $cookie['domain'] = $attributeValue,
                'secure' => $cookie['secure'] = true,
                'httponly' => $cookie['httpOnly'] = true,
                'samesite' => $cookie['sameSite'] = $attributeValue,
                default => null,
            };
        }

        if ($maxAge !== null) {
            $cookie['ttl'] = $maxAge > 0 ? $maxAge : -1;
        } elseif (is_int($expires)) {
            $remaining = $expires - time();
            $cookie['ttl'] = $remaining > 0 ? $remaining : -1;
        }

        return $cookie;
    }
}
