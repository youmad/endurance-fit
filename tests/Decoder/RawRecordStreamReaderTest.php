<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\RawRecordStreamReader;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\ResourceFitInput;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\RawCompressedTimestampDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataRecord;
use Youmad\Endurance\Fit\Raw\RawDefinitionMessage;
use Youmad\Endurance\Fit\Raw\RawFitMessage;

final class RawRecordStreamReaderTest extends TestCase
{
    public function testReadsLittleEndianDefinitionAndDataLazily(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x02"
            ."\xFD\x04\x86"
            ."\x03\x01\x02"
            ."\x00"
            ."\x01\x02\x03\x04"
            ."\x7F";

        $input = $this->input($bytes);

        $messages = (new RawRecordStreamReader())->read(
            input: $input,
            dataSize: strlen($bytes),
        );

        self::assertSame(
            0,
            $input->position(),
        );

        $messages = iterator_to_array(
            $messages,
            false,
        );

        self::assertSame(
            strlen($bytes),
            $input->position(),
        );

        self::assertCount(
            2,
            $messages,
        );

        self::assertInstanceOf(
            RawDefinitionMessage::class,
            $messages[0],
        );

        self::assertInstanceOf(
            RawDataMessage::class,
            $messages[1],
        );

        self::assertSame(
            12,
            $messages[1]->byteOffset(),
        );

        self::assertSame(
            "\x01\x02\x03\x04",
            $messages[1]
                ->standardFields()[0]
                ->value
                ->bytes(),
        );

        self::assertSame(
            "\x7F",
            $messages[1]
                ->standardFields()[1]
                ->value
                ->bytes(),
        );
    }

    private function input(
        string $bytes,
    ): ResourceFitInput {
        $stream = fopen(
            'php://memory',
            'r+b',
        );

        self::assertIsResource($stream);

        fwrite(
            $stream,
            $bytes,
        );

        rewind($stream);

        return new ResourceFitInput(
            $stream,
        );
    }

    public function testReadsBigEndianGlobalMessageNumber(): void
    {
        $bytes =
            "\x41"
            ."\x00"
            ."\x01"
            ."\x01\x02"
            ."\x00"
            ."\x01";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDefinitionMessage::class, $messages[0]);

        self::assertSame(
            FitArchitecture::BigEndian,
            $messages[0]->definition->architecture,
        );

        self::assertSame(
            258,
            $messages[0]->definition->globalMessageNumber,
        );
    }

    public function testReadsDeveloperFieldDefinitionsAndValues(): void
    {
        $bytes =
            "\x60"
            ."\x00"
            ."\x00"
            ."\x14\x00"
            ."\x00"
            ."\x01"
            ."\x07\x02\x03"
            ."\x00"
            ."\xAA\xBB";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDataRecord::class, $messages[1]);

        self::assertSame(
            "\xAA\xBB",
            $messages[1]
                ->developerFields()[0]
                ->value
                ->bytes(),
        );
    }

    public function testZeroSizedStandardFieldConsumesNoPayloadBytes(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x02"
            ."\x01\x00\x02"
            ."\x03\x01\x02"
            ."\x00"
            ."\x96";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDefinitionMessage::class, $messages[0]);
        self::assertInstanceOf(RawDataRecord::class, $messages[1]);

        self::assertCount(
            2,
            $messages[0]->definition->standardFields(),
        );

        self::assertSame(
            0,
            $messages[0]
                ->definition
                ->standardFields()[0]
                ->size,
        );

        self::assertCount(
            1,
            $messages[1]->standardFields(),
        );

        self::assertSame(
            3,
            $messages[1]
                ->standardFields()[0]
                ->definition
                ->fieldNumber,
        );

        self::assertSame(
            "\x96",
            $messages[1]
                ->standardFields()[0]
                ->value
                ->bytes(),
        );
    }

    public function testZeroSizedDeveloperFieldConsumesNoPayloadBytes(): void
    {
        $bytes =
            "\x60\x00\x00\x14\x00\x00"
            ."\x02"
            ."\x07\x00\x03"
            ."\x08\x02\x03"
            ."\x00"
            ."\xAA\xBB";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDefinitionMessage::class, $messages[0]);
        self::assertInstanceOf(RawDataRecord::class, $messages[1]);

        self::assertCount(
            2,
            $messages[0]->definition->developerFields(),
        );

        self::assertSame(
            0,
            $messages[0]
                ->definition
                ->developerFields()[0]
                ->size,
        );

        self::assertCount(
            1,
            $messages[1]->developerFields(),
        );

        self::assertSame(
            8,
            $messages[1]
                ->developerFields()[0]
                ->definition
                ->fieldNumber,
        );

        self::assertSame(
            "\xAA\xBB",
            $messages[1]
                ->developerFields()[0]
                ->value
                ->bytes(),
        );
    }

    public function testReadsIdenticalDuplicateStandardFieldsFromWire(): void
    {
        $bytes =
            "\x40\x00\x00\x17\x00\x02"
            ."\x1B\x09\x07"
            ."\x1B\x09\x07"
            ."\x00"
            ."Watch7,5\x00"
            ."Watch7,5\x00";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDefinitionMessage::class, $messages[0]);
        self::assertInstanceOf(RawDataRecord::class, $messages[1]);

        self::assertCount(
            2,
            $messages[0]->definition->standardFields(),
        );

        self::assertCount(
            2,
            $messages[1]->standardFields(),
        );

        self::assertSame(
            "Watch7,5\x00",
            $messages[1]
                ->standardFields()[0]
                ->value
                ->bytes(),
        );

        self::assertSame(
            "Watch7,5\x00",
            $messages[1]
                ->standardFields()[1]
                ->value
                ->bytes(),
        );
    }

    public function testReadsConflictingDuplicateStandardFieldsFromWire(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x03"
            ."\x03\x01\x02"
            ."\x03\x02\x84"
            ."\x04\x01\x02"
            ."\x00"
            ."\x7F"
            ."\x34\x12"
            ."\xAA";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDefinitionMessage::class, $messages[0]);
        self::assertInstanceOf(RawDataRecord::class, $messages[1]);

        self::assertCount(
            3,
            $messages[0]->definition->standardFields(),
        );

        self::assertCount(
            3,
            $messages[1]->standardFields(),
        );

        self::assertSame(
            "\x7F",
            $messages[1]
                ->standardFields()[0]
                ->value
                ->bytes(),
        );

        self::assertSame(
            "\x34\x12",
            $messages[1]
                ->standardFields()[1]
                ->value
                ->bytes(),
        );

        self::assertSame(
            "\xAA",
            $messages[1]
                ->standardFields()[2]
                ->value
                ->bytes(),
        );
    }

    public function testReadsDuplicateDeveloperFieldIdentityFromWire(): void
    {
        $bytes =
            "\x60\x00\x00\x14\x00\x00"
            ."\x02"
            ."\x07\x01\x03"
            ."\x07\x02\x03"
            ."\x00"
            ."\xAA"
            ."\xBB\xCC";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDefinitionMessage::class, $messages[0]);
        self::assertInstanceOf(RawDataRecord::class, $messages[1]);

        self::assertCount(
            2,
            $messages[0]->definition->developerFields(),
        );

        self::assertCount(
            2,
            $messages[1]->developerFields(),
        );

        self::assertSame(
            "\xAA",
            $messages[1]
                ->developerFields()[0]
                ->value
                ->bytes(),
        );

        self::assertSame(
            "\xBB\xCC",
            $messages[1]
                ->developerFields()[1]
                ->value
                ->bytes(),
        );
    }

    public function testLocalMessageDefinitionMayBeReplaced(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x00"
            ."\x00"
            ."\x40\x00\x00\x15\x00\x00"
            ."\x00";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawDataRecord::class, $messages[1]);
        self::assertInstanceOf(RawDataRecord::class, $messages[3]);

        self::assertSame(
            20,
            $messages[1]->globalMessageNumber(),
        );

        self::assertSame(
            21,
            $messages[3]->globalMessageNumber(),
        );

        self::assertNotSame(
            $messages[1]->definition(),
            $messages[3]->definition(),
        );
    }

    public function testReadsCompressedTimestampWithPayloadDefinitionThatOmitsTimestamp(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x01"
            ."\xFD\x04\x86"
            ."\x00"
            ."\xE8\x03\x00\x00"
            ."\x41\x00\x00\x14\x00\x01"
            ."\x03\x01\x02"
            ."\xAD"
            ."\x7F";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertCount(
            4,
            $messages,
        );

        self::assertInstanceOf(
            RawCompressedTimestampDataMessage::class,
            $messages[3],
        );

        self::assertSame(
            23,
            $messages[3]->byteOffset(),
        );

        self::assertSame(
            1005,
            $messages[3]->reconstructedTimestamp,
        );

        self::assertCount(
            1,
            $messages[3]->standardFields(),
        );

        self::assertSame(
            3,
            $messages[3]
                ->standardFields()[0]
                ->definition
                ->fieldNumber,
        );

        self::assertSame(
            "\x7F",
            $messages[3]
                ->standardFields()[0]
                ->value
                ->bytes(),
        );
    }

    public function testCompressedTimestampMayReusePreviousTimestamp(): void
    {
        $messages = $this->compressedMessages(
            fullTimestampBytes: "\xE8\x03\x00\x00",
            compressedHeaders: "\xA8",
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);

        self::assertSame(
            1000,
            $messages[3]->reconstructedTimestamp,
        );
    }

    /**
     * @return list<RawFitMessage>
     */
    private function compressedMessages(
        string $fullTimestampBytes,
        string $compressedHeaders,
    ): array {
        $bytes =
            "\x40\x00\x00\x14\x00\x01"
            ."\xFD\x04\x86"
            ."\x00"
            .$fullTimestampBytes
            ."\x41\x00\x00\x14\x00\x00"
            .$compressedHeaders;

        return iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );
    }

    public function testCompressedTimestampMovesIntoNextThirtyTwoSecondWindow(): void
    {
        $messages = $this->compressedMessages(
            fullTimestampBytes: "\xFF\x03\x00\x00",
            compressedHeaders: "\xA2",
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);

        self::assertSame(
            1026,
            $messages[3]->reconstructedTimestamp,
        );
    }

    public function testReconstructedTimestampBecomesNextReference(): void
    {
        $messages = $this->compressedMessages(
            fullTimestampBytes: "\xE8\x03\x00\x00",
            compressedHeaders: "\xAD\xB2",
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);
        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[4]);

        self::assertSame(
            1005,
            $messages[3]->reconstructedTimestamp,
        );

        self::assertSame(
            1010,
            $messages[4]->reconstructedTimestamp,
        );
    }

    public function testBigEndianTimestampCanSeedCompressedTimestamp(): void
    {
        $bytes =
            "\x40\x00\x01\x00\x14\x01"
            ."\xFD\x04\x86"
            ."\x00"
            ."\x00\x00\x03\xE8"
            ."\x41\x00\x00\x14\x00\x00"
            ."\xAD";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);

        self::assertSame(
            1005,
            $messages[3]->reconstructedTimestamp,
        );
    }

    public function testCompressedTimestampUsesZeroOriginBeforeFullTimestamp(): void
    {
        $bytes =
            "\x41\x00\x00\x14\x00\x00"
            ."\xAD";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[1]);

        self::assertSame(
            13,
            $messages[1]->reconstructedTimestamp,
        );
    }

    public function testInvalidFullTimestampLeavesZeroOriginUntouched(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x01"
            ."\xFD\x04\x86"
            ."\x00"
            ."\xFF\xFF\xFF\xFF"
            ."\x41\x00\x00\x14\x00\x00"
            ."\xAD";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);

        self::assertSame(
            13,
            $messages[3]->reconstructedTimestamp,
        );
    }

    public function testPhysicalTimestampFieldInCompressedPayloadIsConsumedAndCanReseedAccumulator(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x01"
            ."\xFD\x04\x86"
            ."\x00"
            ."\xE8\x03\x00\x00"
            ."\x41\x00\x00\x14\x00\x01"
            ."\xFD\x04\x86"
            ."\xAD"
            ."\xD0\x07\x00\x00"
            ."\xB1"
            ."\xB8\x0B\x00\x00";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);
        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[4]);

        self::assertSame(
            1005,
            $messages[3]->reconstructedTimestamp,
        );

        self::assertSame(
            "\xD0\x07\x00\x00",
            $messages[3]
                ->standardFields()[0]
                ->value
                ->bytes(),
        );

        self::assertSame(
            2001,
            $messages[4]->reconstructedTimestamp,
        );
    }

    public function testLastValidDuplicateFullTimestampSeedsCompressedTimestamp(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x02"
            ."\xFD\x04\x86"
            ."\xFD\x04\x86"
            ."\x00"
            ."\xE8\x03\x00\x00"
            ."\xD0\x07\x00\x00"
            ."\x41\x00\x00\x14\x00\x00"
            ."\xB1";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);

        self::assertSame(
            2001,
            $messages[3]->reconstructedTimestamp,
        );
    }

    public function testNormalTimestampResetsReferenceAfterCompressedMessages(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x01"
            ."\xFD\x04\x86"
            ."\x00"
            ."\xE8\x03\x00\x00"
            ."\x41\x00\x00\x14\x00\x00"
            ."\xAD"
            ."\x00"
            ."\xD0\x07\x00\x00"
            ."\xB1";

        $messages = iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
            false,
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);
        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[5]);

        self::assertSame(
            1005,
            $messages[3]->reconstructedTimestamp,
        );

        self::assertSame(
            2001,
            $messages[5]->reconstructedTimestamp,
        );
    }

    public function testCompressedTimestampWrapsUnsignedThirtyTwoBitRange(): void
    {
        $messages = $this->compressedMessages(
            fullTimestampBytes: "\xFE\xFF\xFF\xFF",
            compressedHeaders: "\xA1",
        );

        self::assertInstanceOf(RawCompressedTimestampDataMessage::class, $messages[3]);

        self::assertSame(
            1,
            $messages[3]->reconstructedTimestamp,
        );
    }

    public function testRejectsDataWithoutActiveDefinition(): void
    {
        $this->expectException(
            FitDecodeException::class,
        );

        iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input("\x00"),
                dataSize: 1,
            ),
        );
    }

    public function testRejectsCompressedDataWithoutActiveDefinition(): void
    {
        $this->expectException(
            FitDecodeException::class,
        );

        $this->expectExceptionMessage(
            'references undefined local message 0',
        );

        iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input("\x80"),
                dataSize: 1,
            ),
        );
    }

    public function testRejectsUnsupportedArchitecture(): void
    {
        $bytes =
            "\x40"
            ."\x00"
            ."\x02"
            ."\x14\x00"
            ."\x00";

        $this->expectException(
            FitDecodeException::class,
        );

        $this->expectExceptionMessage(
            'Unsupported FIT architecture',
        );

        iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
        );
    }

    public function testFieldCannotCrossDeclaredDataBoundary(): void
    {
        $bytes =
            "\x40\x00\x00\x14\x00\x01"
            ."\x03\x04\x86"
            ."\x00"
            ."\x01\x02";

        $this->expectException(
            FitDecodeException::class,
        );

        $this->expectExceptionMessage(
            'exceeds the declared data section',
        );

        iterator_to_array(
            (new RawRecordStreamReader())->read(
                input: $this->input($bytes),
                dataSize: strlen($bytes),
            ),
        );
    }
}
