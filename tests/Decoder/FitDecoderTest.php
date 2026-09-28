<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\FitDecoder;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\ResourceFitInput;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\InMemoryFitProfileRegistry;
use Youmad\Endurance\Fit\Profile\InMemoryFitTypeRegistry;
use Youmad\Endurance\Fit\Profile\MessageProfile;
use Youmad\Endurance\Fit\Typed\TypedFieldElements;
use Youmad\Endurance\Fit\Typed\TypedScalarFieldElement;
use Youmad\Endurance\Fit\Unified\UnifiedDataMessage;

final class FitDecoderTest extends TestCase
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

    public function testOpensAndDecodesCompleteFitFile(): void
    {
        $file = $this->decoder()->open(
            $this->input(
                self::HEADER_WITH_CRC
                .self::DATA_SECTION
                ."\x23\x28",
            ),
        );

        self::assertSame(
            14,
            $file->header->headerSize,
        );

        self::assertSame(
            21_208,
            $file->header->profileVersion,
        );

        self::assertFalse(
            $file->isCompleted(),
        );

        $messages = iterator_to_array(
            $file->messages(),
        );

        self::assertSame(
            [1],
            array_keys($messages),
        );

        $message = $messages[1];

        self::assertSame(
            'record',
            $message->name(),
        );

        self::assertSame(
            20,
            $message->globalMessageNumber(),
        );

        self::assertSame(
            0x04030201,
            $this->physicalScalar(
                $message,
                253,
            ),
        );

        self::assertSame(
            127,
            $this->physicalScalar(
                $message,
                3,
            ),
        );

        self::assertTrue(
            $file->isCompleted(),
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

    private function decoder(): FitDecoder
    {
        return new FitDecoder(
            profiles: new InMemoryFitProfileRegistry(
                MessageProfile::create(
                    globalMessageNumber: 20,
                    name: 'record',
                    fields: [
                        FieldProfile::create(
                            fieldNumber: 253,
                            name: 'timestamp',
                            typeName: 'uint32',
                            units: 's',
                        ),
                        FieldProfile::create(
                            fieldNumber: 3,
                            name: 'heart_rate',
                            typeName: 'uint8',
                            units: 'bpm',
                        ),
                    ],
                ),
            ),
            types: new InMemoryFitTypeRegistry(),
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

    private function physicalScalar(
        UnifiedDataMessage $message,
        int $fieldNumber,
    ): int|float|string {
        $physical = $message
            ->standardField($fieldNumber)
            ?->physical();

        self::assertNotNull($physical);

        $typedValue = $physical->source->value;

        self::assertInstanceOf(
            TypedFieldElements::class,
            $typedValue,
        );

        $typedElement = $typedValue
            ->elements()[0];

        self::assertInstanceOf(
            TypedScalarFieldElement::class,
            $typedElement,
        );

        return $typedElement->value();
    }

    public function testUnifiedFileStreamCanOnlyBeConsumedOnce(): void
    {
        $file = $this->decoder()->open(
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

        $this->expectExceptionMessage(
            'can only be consumed once',
        );

        iterator_to_array(
            $file->messages(),
        );
    }
}
