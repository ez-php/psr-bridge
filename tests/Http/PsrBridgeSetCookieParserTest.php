<?php

declare(strict_types=1);

namespace Tests\Http;

use EzPhp\PsrBridge\Http\SetCookieParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class PsrBridgeSetCookieParserTest
 *
 * @package Tests\Http
 */
#[CoversClass(SetCookieParser::class)]
final class PsrBridgeSetCookieParserTest extends TestCase
{
    public function testMinimalCookie(): void
    {
        $this->assertSame([
            'name' => 'a',
            'value' => 'b=c',
            'ttl' => 0,
            'path' => '',
            'domain' => '',
            'secure' => false,
            'httpOnly' => false,
            'sameSite' => '',
        ], SetCookieParser::parse('a=b=c; Unknown=1'));
    }

    public function testExpiresBecomesTtlWhenNoMaxAge(): void
    {
        $future = SetCookieParser::parse('a=b; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', time() + 600));
        $past = SetCookieParser::parse('a=b; Expires=Thu, 01 Jan 1970 00:00:00 GMT');
        $both = SetCookieParser::parse('a=b; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=30');

        $this->assertGreaterThan(590, $future['ttl']);
        $this->assertLessThanOrEqual(600, $future['ttl']);
        $this->assertSame(-1, $past['ttl']);
        $this->assertSame(30, $both['ttl']);
    }

    public function testRejectsValueWithoutName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SetCookieParser::parse('=value');
    }

    public function testRejectsValueWithoutPair(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SetCookieParser::parse('justtext');
    }
}
