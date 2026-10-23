<?php

declare(strict_types=1);

namespace EzPhp\PsrBridge;

/**
 * Class BridgeException
 *
 * A value cannot be converted between the ez-php and PSR representations —
 * e.g. a RequestInterface implementation other than ez-php/http's Request, an
 * SSE response (an endless stream cannot be buffered into a PSR-7 body), or a
 * Set-Cookie header ez-php's Cookie rejects.
 *
 * @package EzPhp\PsrBridge
 */
final class BridgeException extends \RuntimeException
{
}
