<?php

declare(strict_types=1);

namespace EzPhp\PsrBridge\Http;

use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\UploadedFile;
use EzPhp\PsrBridge\BridgeException;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class RequestConverter
 *
 * Converts ez-php/http's Request to a PSR-7 ServerRequest and back. The PSR-7
 * objects come from the application's own PSR-17 factories — this module
 * ships no PSR-7 implementation.
 *
 * Mapping: method, URI (absolute when a Host is known), headers, query,
 * parsed body, cookies, server params, raw body, uploaded files, and route
 * params ↔ request attributes.
 *
 * @package EzPhp\PsrBridge\Http
 */
final readonly class RequestConverter
{
    /**
     * @param ServerRequestFactoryInterface $requests
     * @param StreamFactoryInterface        $streams
     * @param UploadedFileFactoryInterface  $uploadedFiles
     */
    public function __construct(
        private ServerRequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
        private UploadedFileFactoryInterface $uploadedFiles,
    ) {
    }

    /**
     * ez-php Request → PSR-7 ServerRequest.
     *
     * @param RequestInterface $request
     *
     * @throws BridgeException When `$request` is not ez-php/http's Request (only it exposes the whole request).
     *
     * @return ServerRequestInterface
     */
    public function toPsr(RequestInterface $request): ServerRequestInterface
    {
        if (!$request instanceof Request) {
            throw new BridgeException(sprintf('Only %s can be converted to PSR-7, got %s.', Request::class, $request::class));
        }

        $psr = $this->requests
            ->createServerRequest($request->method(), $this->absoluteUri($request), $request->serverParams())
            ->withQueryParams($request->queryParams())
            ->withCookieParams($request->cookies())
            ->withParsedBody($request->bodyParams() === [] ? null : $request->bodyParams())
            ->withBody($this->streams->createStream($request->rawBody()))
            ->withUploadedFiles(array_map($this->toPsrFile(...), $request->files()));

        foreach ($request->headers() as $name => $value) {
            $values = $this->headerValues($value);

            if ($values !== []) {
                $psr = $psr->withHeader($name, $values);
            }
        }

        foreach ($request->params() as $name => $value) {
            $psr = $psr->withAttribute($name, $value);
        }

        return $psr;
    }

    /**
     * PSR-7 ServerRequest → ez-php Request.
     *
     * Uploaded files without a local file behind their stream are copied to a
     * temporary file, since ez-php's UploadedFile is path-based; nested
     * upload arrays are skipped (ez-php's files are flat). Attributes become
     * route params.
     *
     * @param ServerRequestInterface $request
     *
     * @return Request
     */
    public function fromPsr(ServerRequestInterface $request): Request
    {
        $uri = $request->getUri();
        $path = $uri->getPath() === '' ? '/' : $uri->getPath();
        $query = $uri->getQuery();

        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[(string) $name] = implode(', ', $values);
        }

        $parsedBody = $request->getParsedBody();
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $rawBody = $body->getContents();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $files = [];

        foreach ($request->getUploadedFiles() as $field => $file) {
            if ($file instanceof UploadedFileInterface) {
                $files[(string) $field] = $this->fromPsrFile($file);
            }
        }

        return new Request(
            method: $request->getMethod(),
            uri: $path . ($query !== '' ? '?' . $query : ''),
            query: $this->stringKeyed($request->getQueryParams()),
            body: is_array($parsedBody) ? $this->stringKeyed($parsedBody) : ($parsedBody !== null ? get_object_vars($parsedBody) : []),
            headers: $headers,
            cookies: $this->stringKeyed($request->getCookieParams()),
            server: $this->stringKeyed($request->getServerParams()),
            rawBody: $rawBody,
            params: $this->stringKeyed($request->getAttributes()),
            files: $files,
        );
    }

    /**
     * The request URI, made absolute from the Host header (and HTTPS server param) when available.
     */
    private function absoluteUri(Request $request): string
    {
        $host = $request->header('host');

        if (!is_string($host) || $host === '') {
            return $request->uri();
        }

        $https = $request->server('HTTPS');
        $scheme = is_string($https) && $https !== '' && strtolower($https) !== 'off' ? 'https' : 'http';

        return $scheme . '://' . $host . $request->uri();
    }

    /**
     * @return list<string>
     */
    private function headerValues(mixed $value): array
    {
        $values = [];

        foreach (is_array($value) ? $value : [$value] as $item) {
            if (is_scalar($item)) {
                $values[] = (string) $item;
            }
        }

        return $values;
    }

    private function toPsrFile(UploadedFile $file): UploadedFileInterface
    {
        $readable = $file->error() === UPLOAD_ERR_OK && is_file($file->tmpName()) && is_readable($file->tmpName());

        return $this->uploadedFiles->createUploadedFile(
            $readable ? $this->streams->createStreamFromFile($file->tmpName()) : $this->streams->createStream(''),
            $file->size(),
            $file->error(),
            $file->originalName(),
            $file->mimeType(),
        );
    }

    private function fromPsrFile(UploadedFileInterface $file): UploadedFile
    {
        $path = '';

        if ($file->getError() === UPLOAD_ERR_OK) {
            $stream = $file->getStream();
            $uri = $stream->getMetadata('uri');

            if (is_string($uri) && is_file($uri)) {
                $path = $uri;
            } else {
                $path = (string) tempnam(sys_get_temp_dir(), 'ezpsr');

                if ($stream->isSeekable()) {
                    $stream->rewind();
                }

                file_put_contents($path, $stream->getContents());
            }
        }

        return new UploadedFile(
            $file->getClientFilename() ?? '',
            $file->getClientMediaType() ?? '',
            $file->getSize() ?? 0,
            $path,
            $file->getError(),
        );
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
