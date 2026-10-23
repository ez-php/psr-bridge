<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * PSR-3 logger that records every call as [level, message, context].
 *
 * @package Tests\Support
 */
final class PsrBridgeRecordingPsrLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<array{mixed, string, array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
