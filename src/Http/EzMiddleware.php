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
 * Class EzMiddleware
 *
 * The reverse of PsrMiddleware: exposes an ez-php middleware as a PSR-15
 * middleware, so it can run in a PSR-15 stack (another framework, a PSR-15
 * dispatcher).
 *
 * @package EzPhp\PsrBridge\Http
 */
final readonly class EzMiddleware implements PsrMiddlewareInterface
{
    /**
     * @param MiddlewareInterface $middleware
     * @param RequestConverter    $requests
     * @param ResponseConverter   $responses
     */
    public function __construct(
        private MiddlewareInterface $middleware,
        private RequestConverter $requests,
        private ResponseConverter $responses,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): PsrResponseInterface
    {
        $next = fn (RequestInterface $ezRequest): ResponseInterface => $this->responses->fromPsr(
            $handler->handle($this->requests->toPsr($ezRequest))
        );

        return $this->responses->toPsr($this->middleware->handle($this->requests->fromPsr($request), $next));
    }
}
