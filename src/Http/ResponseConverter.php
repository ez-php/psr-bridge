<?php

declare(strict_types=1);

namespace EzPhp\PsrBridge\Http;

use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\PsrBridge\BridgeException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class ResponseConverter
 *
 * Converts ez-php/http responses to PSR-7 and PSR-7 responses to ez-php's
 * Response.
 *
 * ez → PSR: the body is buffered via writeBody(), so a StreamedResponse
 * (e.g. a download) works; an SSE response (`text/event-stream`) is rejected,
 * because an event stream may never end. Cookies become `Set-Cookie` headers.
 *
 * PSR → ez: `Set-Cookie` headers are parsed back into cookies (ez-php's
 * Response keeps cookies separate from headers); other multi-value headers
 * are joined with ", ".
 *
 * @package EzPhp\PsrBridge\Http
 */
final readonly class ResponseConverter
{
    /**
     * @param ResponseFactoryInterface $responses
     * @param StreamFactoryInterface   $streams
     */
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {
    }

    /**
     * ez-php response → PSR-7 response.
     *
     * @param ResponseInterface $response
     *
     * @throws BridgeException For an SSE (`text/event-stream`) response.
     *
     * @return PsrResponseInterface
     */
    public function toPsr(ResponseInterface $response): PsrResponseInterface
    {
        $headers = $response->headers();

        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'content-type' && str_starts_with(strtolower(trim($value)), 'text/event-stream')) {
                throw new BridgeException('An SSE (text/event-stream) response cannot be converted to PSR-7: the stream may never end.');
            }
        }

        $body = '';
        $response->writeBody(static function (string $chunk) use (&$body): void {
            $body .= $chunk;
        });

        $psr = $this->responses->createResponse($response->status())->withBody($this->streams->createStream($body));

        foreach ($headers as $name => $value) {
            $psr = $psr->withHeader($name, $value);
        }

        foreach ($response->cookies() as $cookie) {
            $psr = $psr->withAddedHeader('Set-Cookie', $cookie->toHeaderValue());
        }

        return $psr;
    }

    /**
     * PSR-7 response → ez-php Response.
     *
     * @param PsrResponseInterface $response
     *
     * @throws BridgeException When a header or Set-Cookie value is rejected by ez-php/http.
     *
     * @return Response
     */
    public function fromPsr(PsrResponseInterface $response): Response
    {
        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $ez = new Response($stream->getContents(), $response->getStatusCode());

        try {
            foreach ($response->getHeaders() as $name => $values) {
                $name = (string) $name;

                if (strtolower($name) !== 'set-cookie') {
                    $ez = $ez->withHeader($name, implode(', ', $values));

                    continue;
                }

                foreach ($values as $value) {
                    $cookie = SetCookieParser::parse($value);
                    $ez = $ez->withCookie(...$cookie);
                }
            }
        } catch (InvalidArgumentException $e) {
            throw new BridgeException('PSR-7 response cannot be converted: ' . $e->getMessage(), 0, $e);
        }

        return $ez;
    }
}
