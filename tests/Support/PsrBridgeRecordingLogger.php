<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Logging\LoggerInterface;
use EzPhp\Logging\LogLevel;

/**
 * ez-php logger that records every call as [level, message, context].
 *
 * @package Tests\Support
 */
final class PsrBridgeRecordingLogger implements LoggerInterface
{
    /** @var list<array{string, string, array<string, mixed>}> */
    public array $records = [];

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        $this->records[] = [$level->value, $message, $context];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }
}
