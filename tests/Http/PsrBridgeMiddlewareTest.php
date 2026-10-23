<?php

declare(strict_types=1);

namespace Tests\Http;

use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\PsrBridge\Http\EzMiddleware;
use EzPhp\PsrBridge\Http\PsrMiddleware;
use EzPhp\PsrBridge\Http\RequestConverter;
use EzPhp\PsrBridge\Http\ResponseConverter;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tests\TestCase;

/**
 * Class PsrBridgeMiddlewareTest
 *
 * @package Tests\Http
 */
#[CoversClass(PsrMiddleware::class)]
#[CoversClass(EzMiddleware::class)]
#[UsesClass(RequestConverter::class)]
#[UsesClass(ResponseConverter::class)]
final class PsrBridgeMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    private RequestConverter $requests;

    private ResponseConverter $responses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Psr17Factory();
        $this->requests = new RequestConverter($this->factory, $this->factory, $this->factory);
        $this->responses = new ResponseConverter($this->factory, $this->factory);
    }

    public function testPsr15MiddlewareRunsInsideEzPipeline(): void
    {
        $psrMiddleware = new class () implements PsrMiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): PsrResponseInterface
            {
                return $handler->handle($request->withAttribute('user', 'ada')->withHeader('X-Seen', 'psr'))
                    ->withHeader('X-Wrapped', 'yes');
            }
        };

        $adapter = new PsrMiddleware($psrMiddleware, $this->requests, $this->responses);
        $next = static function (RequestInterface $request): ResponseInterface {
            $user = $request->param('user');
            $seen = $request->header('x-seen');

            return new Response(sprintf('%s/%s', is_string($user) ? $user : '', is_string($seen) ? $seen : ''));
        };

        $response = $adapter->handle(new Request('GET', '/'), $next);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('ada/psr', $response->body());
        $this->assertSame('yes', $response->headers()['X-Wrapped'] ?? null);
    }

    public function testEzMiddlewareRunsInsidePsr15Stack(): void
    {
        $ezMiddleware = new class () implements MiddlewareInterface {
            public function handle(RequestInterface $request, callable $next): ResponseInterface
            {
                $response = $next($request instanceof Request ? $request->withParams(['tenant' => 't1']) : $request);

                return $response->withHeader('X-Ez', 'done');
            }
        };

        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): PsrResponseInterface
            {
                $tenant = $request->getAttribute('tenant');

                return (new Psr17Factory())->createResponse(202)->withBody((new Psr17Factory())->createStream(is_string($tenant) ? $tenant : ''));
            }
        };

        $response = (new EzMiddleware($ezMiddleware, $this->requests, $this->responses))
            ->process($this->factory->createServerRequest('POST', '/x'), $handler);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('t1', (string) $response->getBody());
        $this->assertSame(['done'], $response->getHeader('x-ez'));
    }
}
