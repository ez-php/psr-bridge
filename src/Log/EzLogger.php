<?php

declare(strict_types=1);

namespace EzPhp\PsrBridge\Log;

use EzPhp\Logging\LoggerInterface;
use EzPhp\Logging\LogLevel;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Class EzLogger
 *
 * An ez-php/logging logger backed by a PSR-3 logger (e.g. Monolog) — bind it
 * as `EzPhp\Logging\LoggerInterface` to route the framework's logging into an
 * existing PSR-3 setup. Every ez level exists in PSR-3, so the mapping is 1:1.
 *
 * Requires ez-php/logging and psr/log (both suggested, not required).
 *
 * @package EzPhp\PsrBridge\Log
 */
final readonly class EzLogger implements LoggerInterface
{
    /**
     * @param PsrLoggerInterface $logger
     */
    public function __construct(private PsrLoggerInterface $logger)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->logger->log($level->value, $message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }
}
