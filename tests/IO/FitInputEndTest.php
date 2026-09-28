<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\IO;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Checksum\FitCrc16;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\BufferedFitInput;
use Youmad\Endurance\Fit\IO\CrcTrackingFitInput;
use Youmad\Endurance\Fit\IO\ResourceFitInput;

final class FitInputEndTest extends TestCase
{
    public function testProbePreservesBytesPositionAndCrc(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        try {
            fwrite($stream, 'abc');
            rewind($stream);
            $crc = new FitCrc16();
            $input = new CrcTrackingFitInput(new ResourceFitInput($stream), $crc);
            self::assertFalse($input->isAtEnd());
            self::assertFalse($input->isAtEnd());
            self::assertSame(0, $input->position());
            self::assertSame(0, $crc->value());
            self::assertSame('', $input->readExact(0));
            self::assertSame('a', $input->readExact(1));
            self::assertFalse($input->isAtEnd());
            self::assertSame('bc', $input->readExact(2));
            self::assertTrue($input->isAtEnd());
            self::assertTrue($input->isAtEnd());
            self::assertSame(3, $input->position());
            self::assertSame(FitCrc16::calculate('abc'), $crc->value());
            $this->expectException(FitDecodeException::class);
            $input->readExact(1);
        } finally {
            fclose($stream);
        }
    }

    public function testBufferedEndIsItsDeclaredBoundaryNotUnderlyingEof(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        try {
            fwrite($stream, 'abcTRAILER');
            rewind($stream);
            $source = new ResourceFitInput($stream);
            $input = new BufferedFitInput($source, 3, 3);
            self::assertFalse($input->isAtEnd());
            self::assertSame('a', $input->readExact(1));
            self::assertFalse($input->isAtEnd());
            self::assertSame('bc', $input->readExact(2));
            self::assertTrue($input->isAtEnd());
            self::assertFalse($source->isAtEnd());
            self::assertSame('TRAILER', $source->readExact(7));
            self::assertTrue($source->isAtEnd());
        } finally {
            fclose($stream);
        }
    }
}
