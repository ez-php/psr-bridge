# ez-php/psr-bridge

Adapters between ez-php's HTTP and logging types and the PSR standards: PSR-7 request/response conversion, PSR-15 middleware in both directions, and PSR-3 loggers in both directions. Bring your own PSR-7 implementation; this package ships none.

## Installation

```bash
composer require ez-php/psr-bridge
composer require nyholm/psr7          # or any other PSR-7 + PSR-17 implementation
composer require ez-php/logging psr/log   # only for the PSR-3 adapters
```

## Converting requests and responses

```php
use EzPhp\PsrBridge\Http\RequestConverter;
use EzPhp\PsrBridge\Http\ResponseConverter;
use Nyholm\Psr7\Factory\Psr17Factory;

$psr17 = new Psr17Factory();
$requests = new RequestConverter($psr17, $psr17, $psr17);   // server-request, stream, uploaded-file factories
$responses = new ResponseConverter($psr17, $psr17);         // response, stream factories

$psrRequest = $requests->toPsr($request);        // ez Request → ServerRequestInterface
$request = $requests->fromPsr($psrRequest);      // and back
$psrResponse = $responses->toPsr($response);     // ez response → ResponseInterface
$response = $responses->fromPsr($psrResponse);   // and back
```

- **Route params become request attributes**, and attributes become route params.
- **Cookies become `Set-Cookie` headers**, which are parsed back into cookies on the way in.
- **Streamed responses** (e.g. downloads) are buffered. An SSE response (`text/event-stream`) throws a `BridgeException`, because an event stream may never end.
- **Only ez-php/http's `Request` converts.** Other `RequestInterface` implementations throw.

## Using a PSR-15 middleware in ez-php

```php
use EzPhp\PsrBridge\Http\PsrMiddleware;

$middleware = new PsrMiddleware(new SomeVendorPsr15Middleware(), $requests, $responses);
// $middleware implements EzPhp\Contracts\MiddlewareInterface
```

ez-php registers middleware by class name, so wrap it in a small application middleware class (or bind one in the container) that constructs the `PsrMiddleware`.

The reverse, an ez-php middleware in a PSR-15 stack: `new EzMiddleware($ezMiddleware, $requests, $responses)`.

## Loggers

```php
use EzPhp\PsrBridge\Log\EzLogger;
use EzPhp\PsrBridge\Log\PsrLogger;

$psrLogger = new PsrLogger($ezLogger);     // give a PSR-3-typed library an ez-php logger
$ezLogger = new EzLogger($monolog);        // route ez-php logging into Monolog (or any PSR-3 logger)
```

PSR-3 has eight levels, ez-php five: `emergency`/`alert` map to `critical`, `notice` to `info`.

## Requirements

Uses the bulk accessors on `EzPhp\Http\Request` (`headers()`, `queryParams()`, `bodyParams()`, `cookies()`, `serverParams()`, `params()`, `files()`) and `UploadedFile::tmpName()`. These need an ez-php/http release that includes them.

## License

MIT
