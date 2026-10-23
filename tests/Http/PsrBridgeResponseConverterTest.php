<?php

declare(strict_types=1);

namespace Tests\Http;

use EzPhp\Http\Response;
use EzPhp\Http\StreamedResponse;
use EzPhp\PsrBridge\BridgeException;
use EzPhp\PsrBridge\Http\ResponseConverter;
use EzPhp\PsrBridge\Http\SetCookieParser;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class PsrBridgeResponseConverterTest
 *
 * @package Tests\Http
 */
#[CoversClass(ResponseConverter::class)]
#[UsesClass(SetCookieParser::class)]
#[UsesClass(BridgeException::class)]
final class PsrBridgeResponseConverterTest extends TestCase
{
    private Psr17Factory $factory;

    private ResponseConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Psr17Factory();
        $this->converter = new ResponseConverter($this->factory, $this->factory);
    }

    public function testToPsrCopiesStatusHeadersBodyAndCookies(): void
    {
        $response = (new Response('{"ok":true}', 201))
            ->withHeader('Content-Type', 'application/json')
            ->withCookie('sid', 'a b', 3600, '/', '', true, true, 'Lax')
            ->withCookie('theme', 'dark');

        $psr = $this->converter->toPsr($response);

        $this->assertSame(201, $psr->getStatusCode());
        $this->assertSame('{"ok":true}', (string) $psr->getBody());
        $this->assertSame(['application/json'], $psr->getHeader('content-type'));
        $this->assertSame([
            'sid=a+b; Max-Age=3600; Path=/; Secure; HttpOnly; SameSite=Lax',
            'theme=dark; Path=/',
        ], $psr->getHeader('set-cookie'));
    }

    public function testToPsrBuffersStreamedResponses(): void
    {
        $response = new StreamedResponse(static function (): \Generator {
            yield 'part 1, ';
            yield 'part 2';
        });

        $this->assertSame('part 1, part 2', (string) $this->converter->toPsr($response)->getBody());
    }

    public function testToPsrRejectsServerSentEvents(): void
    {
        $this->expectException(BridgeException::class);
        $this->expectExceptionMessage('SSE');

        $this->converter->toPsr(StreamedResponse::sse(static function (): \Generator {
            yield from [];
        }));
    }

    public function testFromPsrCopiesStatusHeadersBodyAndParsesCookies(): void
    {
        $body = $this->factory->createStream('hello');
        $body->read(2); // a consumed stream must still be read from the start

        $psr = $this->factory->createResponse(404)
            ->withBody($body)
            ->withHeader('X-Tags', ['a', 'b'])
            ->withAddedHeader('Set-Cookie', 'sid=a+b; Max-Age=60; Path=/app; Domain=shop.test; Secure; HttpOnly; SameSite=Strict')
            ->withAddedHeader('Set-Cookie', 'old=; Max-Age=0');

        $response = $this->converter->fromPsr($psr);

        $this->assertSame(404, $response->status());
        $this->assertSame('hello', $response->body());
        $this->assertSame('a, b', $response->headers()['X-Tags'] ?? null);
        $this->assertArrayNotHasKey('Set-Cookie', $response->headers());

        [$sid, $old] = $response->cookies();
        $this->assertSame('a b', $sid->value());
        $this->assertSame(60, $sid->ttl());
        $this->assertSame('/app', $sid->path());
        $this->assertSame('shop.test', $sid->domain());
        $this->assertTrue($sid->isSecure());
        $this->assertTrue($sid->isHttpOnly());
        $this->assertSame('Strict', $sid->sameSite());
        $this->assertSame(-1, $old->ttl());
    }

    public function testRoundTripPreservesCookies(): void
    {
        $original = (new Response('x'))->withCookie('sid', 'v', 120, '/', 'shop.test', true, true, 'None');

        $copy = $this->converter->fromPsr($this->converter->toPsr($original));

        $this->assertSame($original->cookies()[0]->toHeaderValue(), $copy->cookies()[0]->toHeaderValue());
    }

    public function testFromPsrWrapsCookiesEzRejects(): void
    {
        $this->expectException(BridgeException::class);

        // SameSite=None without Secure is rejected by ez-php's Cookie
        $this->converter->fromPsr($this->factory->createResponse()->withHeader('Set-Cookie', 'a=b; SameSite=None'));
    }
}
