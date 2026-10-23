<?php

declare(strict_types=1);

namespace EzPhp\PsrBridge\Http;

use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class PsrMiddleware
 *
 * Runs a third-party PSR-15 middleware inside ez-php's middleware pipeline:
 * the ez request is converted to PSR-7, the PSR middleware's handler converts
 * back and continues with ez's `$next`, and the PSR response it returns is
 * converted to an ez Response.
 *
 *   $app->middleware(...) takes class strings, so register it via a small
 *   application middleware class or bind it in the container, e.g.
 *   new PsrMiddleware(new SomePsr15Middleware(), $requests, $responses)
 *
 * @package EzPhp\PsrBridge\Http
 */
final readonly class PsrMiddleware implements MiddlewareInterface
{
    /**
     * @param PsrMiddlewareInterface $middleware
     * @param RequestConverter       $requests
     * @param ResponseConverter      $responses
     */
    public function __construct(
        private PsrMiddlewareInterface $middleware,
        private RequestConverter $requests,
        private ResponseConverter $responses,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $handler = new class ($next, $this->requests, $this->responses) implements RequestHandlerInterface {
            /**
             * @param callable(RequestInterface): ResponseInterface $next
             */
            public function __construct(
                private $next,
                private readonly RequestConverter $requests,
                private readonly ResponseConverter $responses,
            ) {
            }

            public function handle(ServerRequestInterface $request): PsrResponseInterface
            {
                return $this->responses->toPsr(($this->next)($this->requests->fromPsr($request)));
            }
        };

        return $this->responses->fromPsr($this->middleware->process($this->requests->toPsr($request), $handler));
    }
}
