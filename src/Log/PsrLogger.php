<?php

declare(strict_types=1);

namespace EzPhp\PsrBridge\Log;

use EzPhp\Logging\LoggerInterface;
use EzPhp\Logging\LogLevel;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel as PsrLogLevel;
use Stringable;

/**
 * Class PsrLogger
 *
 * A PSR-3 logger backed by an ez-php/logging logger — hand it to any library
 * typed against `Psr\Log\LoggerInterface`.
 *
 * ez-php has five levels, PSR-3 eight: `emergency` and `alert` map to
 * `critical`, `notice` to `info`. Messages and context pass through unchanged
 * ({placeholder} interpolation is left to the ez logger, as PSR-3 permits).
 *
 * Requires ez-php/logging and psr/log (both suggested, not required).
 *
 * @package EzPhp\PsrBridge\Log
 */
final class PsrLogger implements PsrLoggerInterface
{
    use LoggerTrait;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @param array<mixed> $context
     *
     * @throws InvalidArgumentException For a level outside PSR-3's eight.
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logger->log(self::level($level), (string) $message, self::context($context));
    }

    /**
     * Map a PSR-3 level onto ez-php's LogLevel.
     *
     * @throws InvalidArgumentException For an unknown level.
     */
    public static function level(mixed $level): LogLevel
    {
        return match ($level) {
            PsrLogLevel::EMERGENCY, PsrLogLevel::ALERT, PsrLogLevel::CRITICAL => LogLevel::CRITICAL,
            PsrLogLevel::ERROR => LogLevel::ERROR,
            PsrLogLevel::WARNING => LogLevel::WARNING,
            PsrLogLevel::NOTICE, PsrLogLevel::INFO => LogLevel::INFO,
            PsrLogLevel::DEBUG => LogLevel::DEBUG,
            default => throw new InvalidArgumentException(sprintf('Unknown PSR-3 log level "%s".', is_scalar($level) ? (string) $level : get_debug_type($level))),
        };
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function context(array $context): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
