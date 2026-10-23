<?php

declare(strict_types=1);

namespace Tests\Log;

use EzPhp\PsrBridge\Log\EzLogger;
use EzPhp\PsrBridge\Log\PsrLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\InvalidArgumentException;
use Stringable;
use Tests\Support\PsrBridgeRecordingLogger;
use Tests\Support\PsrBridgeRecordingPsrLogger;
use Tests\TestCase;

/**
 * Class PsrBridgeLoggerTest
 *
 * @package Tests\Log
 */
#[CoversClass(PsrLogger::class)]
#[CoversClass(EzLogger::class)]
final class PsrBridgeLoggerTest extends TestCase
{
    public function testPsrLoggerMapsAllEightLevelsOntoEzLevels(): void
    {
        $ez = new PsrBridgeRecordingLogger();
        $psr = new PsrLogger($ez);

        $psr->emergency('a');
        $psr->alert('b');
        $psr->critical('c');
        $psr->error('d');
        $psr->warning('e');
        $psr->notice('f');
        $psr->info('g');
        $psr->debug('h', ['k' => 'v', 3 => 'x']);

        $this->assertSame([
            ['critical', 'a', []],
            ['critical', 'b', []],
            ['critical', 'c', []],
            ['error', 'd', []],
            ['warning', 'e', []],
            ['info', 'f', []],
            ['info', 'g', []],
            ['debug', 'h', ['k' => 'v', '3' => 'x']],
        ], $ez->records);
    }

    public function testPsrLoggerAcceptsStringableMessages(): void
    {
        $ez = new PsrBridgeRecordingLogger();

        (new PsrLogger($ez))->info(new class () implements Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        });

        $this->assertSame('stringable', $ez->records[0][1]);
    }

    public function testPsrLoggerRejectsUnknownLevels(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown PSR-3 log level "verbose"');

        (new PsrLogger(new PsrBridgeRecordingLogger()))->log('verbose', 'x');
    }

    public function testEzLoggerForwardsEveryLevelToPsr(): void
    {
        $psr = new PsrBridgeRecordingPsrLogger();
        $ez = new EzLogger($psr);

        $ez->debug('a', ['k' => 1]);
        $ez->info('b');
        $ez->warning('c');
        $ez->error('d');
        $ez->critical('e');
        $ez->log(\EzPhp\Logging\LogLevel::INFO, 'f');

        $this->assertSame([
            ['debug', 'a', ['k' => 1]],
            ['info', 'b', []],
            ['warning', 'c', []],
            ['error', 'd', []],
            ['critical', 'e', []],
            ['info', 'f', []],
        ], $psr->records);
    }
}
