<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\FitFileReader;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Exception\InvalidFitFile;
use Youmad\Endurance\Fit\IO\ResourceFitInput;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawDefinitionMessage;

final class FitFileReaderTest extends TestCase
{
    private const string DATA_SECTION =
        "\x40\x00\x00\x14\x00\x02"
        ."\xFD\x04\x86"
        ."\x03\x01\x02"
        ."\x00"
        ."\x01\x02\x03\x04"
        ."\x7F";

    private const string HEADER_WITH_CRC =
        "\x0E"
        ."\x20"
        ."\xD8\x52"
        ."\x12\x00\x00\x00"
        ."\x2E\x46\x49\x54"
        ."\x7C\x70";

    private const string HEADER_WITHOUT_CRC =
        "\x0C"
        ."\x20"
        ."\xD8\x52"
        ."\x12\x00\x00\x00"
        ."\x2E\x46\x49\x54";

    public function testOpensFourteenByteHeaderWithoutReadingData(): void
    {
        $input = $this->input(
            self::HEADER_WITH_CRC
            .self::DATA_SECTION
            ."\x23\x28",
        );

        $file = (new FitFileReader())->open(
            $input,
        );

        self::assertSame(
            14,
            $input->position(),
        );

        self::assertSame(
            14,
            $file->header->headerSize,
        );

        self::assertSame(
            0x20,
            $file->header->protocolVersion,
        );

        self::assertSame(
            2,
            $file->header->protocolMajorVersion(),
        );

        self::assertSame(
            0,
            $file->header->protocolMinorVersion(),
        );

        self::assertSame(
            21_208,
            $file->header->profileVersion,
        );

        self::assertSame(
            18,
            $file->header->dataSize,
        );

        self::assertSame(
            '.FIT',
            $file->header->dataType,
        );

        self::assertSame(
            0x707C,
            $file->header->headerCrc,
        );

        self::assertFalse(
            $file->isCompleted(),
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

    public function testConsumesMessagesAndVerifiesFileCrc(): void
    {
        $input = $this->input(
            self::HEADER_WITH_CRC
            .self::DATA_SECTION
            ."\x23\x28",
        );

        $file = (new FitFileReader())->open(
            $input,
        );

        $messages = iterator_to_array(
            $file->messages(),
            false,
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
            14,
            $messages[0]->byteOffset(),
        );

        self::assertSame(
            26,
            $messages[1]->byteOffset(),
        );

        self::assertSame(
            34,
            $input->position(),
        );

        self::assertTrue(
            $file->isCompleted(),
        );

        self::assertSame(
            32,
            $file->trailer()->byteOffset,
        );

        self::assertSame(
            0x2823,
            $file->trailer()->declaredCrc,
        );

        self::assertSame(
            0x2823,
            $file->trailer()->calculatedCrc,
        );
    }

    public function testReadsLegacyTwelveByteHeader(): void
    {
        $file = (new FitFileReader())->open(
            $this->input(
                self::HEADER_WITHOUT_CRC
                .self::DATA_SECTION
                ."\xDD\xDB",
            ),
        );

        self::assertSame(
            12,
            $file->header->headerSize,
        );

        self::assertNull(
            $file->header->headerCrc,
        );

        iterator_to_array(
            $file->messages(),
        );

        self::assertSame(
            0xDBDD,
            $file->trailer()->declaredCrc,
        );
    }

    public function testAcceptsZeroHeaderCrc(): void
    {
        $header =
            "\x0E"
            ."\x20"
            ."\xD8\x52"
            ."\x12\x00\x00\x00"
            ."\x2E\x46\x49\x54"
            ."\x00\x00";

        $file = (new FitFileReader())->open(
            $this->input(
                $header
                .self::DATA_SECTION
                ."\x22\x15",
            ),
        );

        self::assertSame(
            0,
            $file->header->headerCrc,
        );

        iterator_to_array(
            $file->messages(),
        );

        self::assertTrue(
            $file->isCompleted(),
        );
    }

    public function testRejectsInvalidHeaderSignature(): void
    {
        $header =
            "\x0C"
            ."\x20"
            ."\xD8\x52"
            ."\x12\x00\x00\x00"
            ."\x2E\x42\x41\x44";

        $this->expectException(
            InvalidFitFile::class,
        );

        (new FitFileReader())->open(
            $this->input($header),
        );
    }

    public function testRejectsHeaderSmallerThanMinimumSize(): void
    {
        $this->expectException(
            InvalidFitFile::class,
        );

        $this->expectExceptionMessage(
            'header size 11',
        );

        (new FitFileReader())->open(
            $this->input("\x0B"),
        );
    }

    public function testReadsExtendedHeaderAndIncludesItInFileCrc(): void
    {
        $header =
            "\x0F"
            ."\x20"
            ."\xD8\x52"
            ."\x12\x00\x00\x00"
            ."\x2E\x46\x49\x54"
            ."\xAA\xBB\xCC";

        $file = (new FitFileReader())->open(
            $this->input(
                $header
                .self::DATA_SECTION
                ."\x70\x38",
            ),
        );

        self::assertSame(
            15,
            $file->header->headerSize,
        );

        self::assertNull(
            $file->header->headerCrc,
        );

        iterator_to_array(
            $file->messages(),
        );

        self::assertSame(
            0x3870,
            $file->trailer()->declaredCrc,
        );
    }

    public function testRejectsUnsupportedProtocolMajorVersion(): void
    {
        $header =
            "\x0C"
            ."\x30"
            ."\xD8\x52"
            ."\x12\x00\x00\x00"
            ."\x2E\x46\x49\x54";

        $this->expectException(
            InvalidFitFile::class,
        );

        $this->expectExceptionMessage(
            'protocol major version 3',
        );

        (new FitFileReader())->open(
            $this->input($header),
        );
    }

    public function testRejectsEmptyDataSection(): void
    {
        $header =
            "\x0C"
            ."\x20"
            ."\xD8\x52"
            ."\x00\x00\x00\x00"
            ."\x2E\x46\x49\x54";

        $this->expectException(
            InvalidFitFile::class,
        );

        (new FitFileReader())->open(
            $this->input($header),
        );
    }

    public function testRejectsInvalidHeaderCrc(): void
    {
        $header =
            "\x0E"
            ."\x20"
            ."\xD8\x52"
            ."\x12\x00\x00\x00"
            ."\x2E\x46\x49\x54"
            ."\x7D\x70";

        $this->expectException(
            InvalidFitFile::class,
        );

        $this->expectExceptionMessage(
            'header CRC mismatch',
        );

        (new FitFileReader())->open(
            $this->input($header),
        );
    }

    public function testFileCrcIsCheckedAfterDataStream(): void
    {
        $file = (new FitFileReader())->open(
            $this->input(
                self::HEADER_WITH_CRC
                .self::DATA_SECTION
                ."\x00\x00",
            ),
        );

        $this->expectException(
            InvalidFitFile::class,
        );

        $this->expectExceptionMessage(
            'file CRC mismatch',
        );

        iterator_to_array(
            $file->messages(),
        );
    }

    public function testTrailerIsUnavailableBeforeStreamCompletion(): void
    {
        $file = (new FitFileReader())->open(
            $this->input(
                self::HEADER_WITH_CRC
                .self::DATA_SECTION
                ."\x23\x28",
            ),
        );

        $this->expectException(
            FitDecodeException::class,
        );

        $file->trailer();
    }

    public function testMessageStreamCanOnlyBeConsumedOnce(): void
    {
        $file = (new FitFileReader())->open(
            $this->input(
                self::HEADER_WITH_CRC
                .self::DATA_SECTION
                ."\x23\x28",
            ),
        );

        iterator_to_array(
            $file->messages(),
        );

        $this->expectException(
            FitDecodeException::class,
        );

        iterator_to_array(
            $file->messages(),
        );
    }

    public function testRejectsTruncatedFileCrc(): void
    {
        $file = (new FitFileReader())->open(
            $this->input(
                self::HEADER_WITH_CRC
                .self::DATA_SECTION
                ."\x23",
            ),
        );

        $this->expectException(
            FitDecodeException::class,
        );

        iterator_to_array(
            $file->messages(),
        );
    }
}
