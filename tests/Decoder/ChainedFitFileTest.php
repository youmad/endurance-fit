<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Checksum\FitCrc16;
use Youmad\Endurance\Fit\Decoder\FitFileReader;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\ResourceFitInput;
use Youmad\Endurance\Fit\Raw\RawCompressedTimestampDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataMessage;

final class ChainedFitFileTest extends TestCase
{
    private const string DEFINITION = "\x40\x00\x00\x14\x00\x01\xFD\x04\x86";

    public function testRetainsDefinitionsAndSequenceAcrossMembers(): void
    {
        $first = self::member(self::DEFINITION."\x00".pack('V', 100));
        $second = self::member("\x00".pack('V', 101));
        $stream = $this->stream($first.$second);
        try {
            $input = new ResourceFitInput($stream);
            $file = (new FitFileReader())->open($input);
            $messages = iterator_to_array($file->messages());
            self::assertSame([0, 1, 2], array_keys($messages));
            self::assertSame(2, $messages[2]->sequence());
            self::assertSame(strlen($first) + 14, $messages[2]->byteOffset());
            self::assertInstanceOf(RawDataMessage::class, $messages[2]);
            self::assertSame(pack('V', 101), $messages[2]->standardFields()[0]->value->bytes());
            self::assertSame(strlen($first.$second), $input->position());
            self::assertTrue($file->isCompleted());
            self::assertSame(strlen($first.$second) - 2, $file->trailer()->byteOffset);
            self::assertSame(0, $file->header->byteOffset);
        } finally {
            fclose($stream);
        }
    }

    public function testAllowsGarminZeroPaddingBeforeNextHeader(): void
    {
        $first = self::member(self::DEFINITION."\x00".pack('V', 100));
        $stream = $this->stream($first."\x00\x00".self::member("\x00".pack('V', 101)));
        try {
            $file = (new FitFileReader())->open(new ResourceFitInput($stream));
            $messages = iterator_to_array($file->messages());
            self::assertCount(3, $messages);
            self::assertSame(strlen($first) + 2 + 14, $messages[2]->byteOffset());
            self::assertTrue($file->isCompleted());
        } finally {
            fclose($stream);
        }
    }

    public function testResetsOnlyCompressedOffsetAtMemberBoundary(): void
    {
        $first = self::member(
            self::DEFINITION."\x00".pack('V', 100)
            ."\x41\x00\x00\x14\x00\x01\x03\x01\x02",
        );
        // Local definition 1 is retained. Garmin nextFile() retains timestamp
        // 100 but resets lastTimeOffset to zero: 100+1, then 101+(3-1).
        $second = self::member("\xA1\x96\xA3\x97");
        $stream = $this->stream($first.$second);
        try {
            $messages = iterator_to_array((new FitFileReader())->open(new ResourceFitInput($stream))->messages());
            self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);
            self::assertSame(101, $messages[3]->reconstructedTimestamp);
            self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[4]);
            self::assertSame(103, $messages[4]->reconstructedTimestamp);
        } finally {
            fclose($stream);
        }
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function compressedPayloadTimestamps(): iterable
    {
        yield 'valid payload reseeds time and offset' => [2_000, 17, 2_001];
        yield 'invalid payload retains compressed state' => [0xFFFFFFFF, 3, 103];
    }

    #[DataProvider('compressedPayloadTimestamps')]
    public function testPhysicalTimestampAfterMemberBoundaryCanReseedAccumulator(
        int $payloadTimestamp,
        int $nextOffset,
        int $expectedTimestamp,
    ): void {
        $first = self::member(
            self::DEFINITION."\x00".pack('V', 100)
            ."\x41\x00\x00\x14\x00\x01\xFD\x04\x86",
        );
        $second = self::member(
            "\xA1".pack('V', $payloadTimestamp)
            .chr(0xA0 | $nextOffset).pack('V', 3_000),
        );
        $stream = $this->stream($first.$second);
        try {
            $file = (new FitFileReader())->open(new ResourceFitInput($stream));
            $messages = iterator_to_array($file->messages());
            self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);
            self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[4]);
            self::assertSame(101, $messages[3]->reconstructedTimestamp);
            self::assertSame(pack('V', $payloadTimestamp), $messages[3]->standardFields()[0]->value->bytes());
            self::assertSame($expectedTimestamp, $messages[4]->reconstructedTimestamp);
            self::assertTrue($file->isCompleted());
        } finally {
            fclose($stream);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function brokenTails(): iterable
    {
        $valid = self::member(self::DEFINITION."\x00".pack('V', 101));
        $badCrc = $valid;
        $last = strlen($badCrc) - 1;
        $badCrc[$last] = chr(ord($badCrc[$last]) ^ 1);
        $badHeader = $valid;
        $badHeader[12] = chr(ord($badHeader[12]) ^ 1);
        yield 'bad second CRC' => [$badCrc];
        yield 'bad second header CRC' => [$badHeader];
        yield 'truncated second header' => [substr($valid, 0, 5)];
        yield 'truncated second payload' => [substr($valid, 0, -4)];
        yield 'truncated second CRC' => [substr($valid, 0, -1)];
        yield 'non-FIT suffix' => ["\x01junk"];
        yield 'padding without a following header' => ["\x00\x00"];
    }

    #[DataProvider('brokenTails')]
    public function testDoesNotReportCompletionWhenLaterMemberFails(string $tail): void
    {
        $stream = $this->stream(self::member(self::DEFINITION."\x00".pack('V', 100)).$tail);
        try {
            $file = (new FitFileReader())->open(new ResourceFitInput($stream));
            try {
                iterator_to_array($file->messages());
                self::fail('A later member must not be silently ignored.');
            } catch (FitDecodeException $failure) {
                self::assertFalse($file->isCompleted());
            }
            $this->expectException(FitDecodeException::class);
            $this->expectExceptionMessage('trailer is unavailable');
            $file->trailer();
        } finally {
            fclose($stream);
        }
    }

    public function testIndependentOpenDoesNotInheritDefinitions(): void
    {
        $reader = new FitFileReader();
        $first = $this->stream(self::member(self::DEFINITION."\x00".pack('V', 100)));
        $second = $this->stream(self::member("\x00".pack('V', 101)));
        try {
            iterator_to_array($reader->open(new ResourceFitInput($first))->messages());
            $this->expectException(FitDecodeException::class);
            $this->expectExceptionMessage('undefined local message');
            iterator_to_array($reader->open(new ResourceFitInput($second))->messages());
        } finally {
            fclose($first);
            fclose($second);
        }
    }

    private static function member(string $data): string
    {
        $header = "\x0e\x20".pack('vV', 21_214, strlen($data)).'.FIT';
        $bytes = $header.pack('v', FitCrc16::calculate($header)).$data;

        return $bytes.pack('v', FitCrc16::calculate($bytes));
    }

    /** @return resource */
    private function stream(string $bytes): mixed
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $bytes);
        rewind($stream);

        return $stream;
    }
}
