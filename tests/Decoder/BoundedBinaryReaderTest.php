<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\BinaryReader;
use Youmad\Endurance\Fit\Decoder\BoundedBinaryReader;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\ResourceFitInput;
use Youmad\Endurance\Fit\Raw\FitArchitecture;

final class BoundedBinaryReaderTest extends TestCase
{
    public function testTracksRemainingBytesAcrossAllReadMethods(): void
    {
        $input = $this->input("\xAA\x01\x02\xBB");
        $reader = new BoundedBinaryReader(
            reader: new BinaryReader($input),
            length: 4,
        );

        self::assertSame(4, $reader->remaining());
        self::assertSame(0, $reader->position());

        self::assertSame(0xAA, $reader->readByte());
        self::assertSame(3, $reader->remaining());
        self::assertSame(1, $reader->position());

        self::assertSame(
            0x0201,
            $reader->readUInt16(
                FitArchitecture::LittleEndian,
            ),
        );
        self::assertSame(1, $reader->remaining());
        self::assertSame(3, $reader->position());

        self::assertSame("\xBB", $reader->readBytes(1));
        self::assertSame(0, $reader->remaining());
        self::assertSame(4, $reader->position());
    }

    public function testRejectedReadDoesNotConsumeRemainingBytes(): void
    {
        $input = $this->input("\xAA\xBB\xCC");
        $reader = new BoundedBinaryReader(
            reader: new BinaryReader($input),
            length: 2,
        );

        self::assertSame(0xAA, $reader->readByte());

        try {
            $reader->readBytes(2);
            self::fail('Expected bounded read to fail.');
        } catch (FitDecodeException $exception) {
            self::assertSame(
                'FIT record at byte 1 exceeds the declared data section by 1 bytes.',
                $exception->getMessage(),
            );
        }

        self::assertSame(1, $reader->remaining());
        self::assertSame(1, $reader->position());
        self::assertSame("\xBB", $reader->readBytes(1));
    }

    private function input(string $bytes): ResourceFitInput
    {
        $stream = fopen(
            'php://memory',
            'r+b',
        );

        self::assertIsResource($stream);

        fwrite($stream, $bytes);
        rewind($stream);

        return new ResourceFitInput($stream);
    }
}
