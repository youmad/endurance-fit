<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\DecodedFieldOrigin;
use Youmad\Endurance\Fit\Decoded\InvalidFieldElement;
use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Decoder\FitDataMessageDecoder;
use Youmad\Endurance\Fit\Raw\DeveloperFieldDefinition;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawCompressedTimestampDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawDefinitionMessage;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final class FitDataMessageDecoderTest extends TestCase
{
    public function testDecodesNormalDataMessage(): void
    {
        $timestampDefinition = $this->timestampDefinition();

        $heartRateDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $developerDefinition = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 2,
            developerDataIndex: 1,
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $timestampDefinition,
                $heartRateDefinition,
            ],
            developerFields: [
                $developerDefinition,
            ],
        );

        $timestampField = new RawStandardField(
            definition: $timestampDefinition,
            value: RawFieldValue::fromBytes(
                "\xE8\x03\x00\x00",
            ),
        );

        $heartRateField = new RawStandardField(
            definition: $heartRateDefinition,
            value: RawFieldValue::fromBytes(
                "\x96",
            ),
        );

        $developerField = new RawDeveloperField(
            definition: $developerDefinition,
            value: RawFieldValue::fromBytes(
                "\xAA\xBB",
            ),
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [
                $timestampField,
                $heartRateField,
            ],
            developerFields: [
                $developerField,
            ],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertSame(
            20,
            $decoded->globalMessageNumber(),
        );

        self::assertSame(
            DecodedFieldOrigin::MessagePayload,
            $decoded->standardFields()[0]->origin,
        );

        self::assertSame(
            $timestampField,
            $decoded->standardFields()[0]->rawField,
        );

        self::assertSame(
            1000,
            $this->validScalar(
                $decoded->standardFields()[0]->value,
            ),
        );

        self::assertSame(
            150,
            $this->validScalar(
                $decoded->standardFields()[1]->value,
            ),
        );

        self::assertSame(
            [$developerField],
            $decoded->developerFields(),
        );
    }

    public function testZeroSizedStandardDefinitionProducesNoDecodedField(): void
    {
        $zeroSizedDefinition = StandardFieldDefinition::create(
            fieldNumber: 1,
            size: 0,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $heartRateDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $zeroSizedDefinition,
                $heartRateDefinition,
            ],
        );

        $heartRateField = new RawStandardField(
            definition: $heartRateDefinition,
            value: RawFieldValue::fromBytes(
                "\x96",
            ),
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [$heartRateField],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertCount(
            1,
            $decoded->standardFields(),
        );

        self::assertSame(
            $heartRateDefinition,
            $decoded->standardFields()[0]->definition,
        );

        self::assertSame(
            150,
            $this->validScalar(
                $decoded->standardFields()[0]->value,
            ),
        );
    }

    public function testCanonicalizesIdenticalDuplicateStandardFields(): void
    {
        $firstDefinition = StandardFieldDefinition::create(
            fieldNumber: 27,
            size: 9,
            baseType: FitBaseType::fromDefinitionByte(
                0x07,
            ),
        );

        $secondDefinition = StandardFieldDefinition::create(
            fieldNumber: 27,
            size: 9,
            baseType: FitBaseType::fromDefinitionByte(
                0x07,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 23,
            standardFields: [
                $firstDefinition,
                $secondDefinition,
            ],
        );

        $firstField = new RawStandardField(
            definition: $firstDefinition,
            value: RawFieldValue::fromBytes(
                "Watch7,5\x00",
            ),
        );

        $secondField = new RawStandardField(
            definition: $secondDefinition,
            value: RawFieldValue::fromBytes(
                "Watch7,5\x00",
            ),
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [
                $firstField,
                $secondField,
            ],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertCount(
            1,
            $decoded->standardFields(),
        );

        self::assertSame(
            $firstDefinition,
            $decoded->standardFields()[0]->definition,
        );

        self::assertSame(
            $firstField,
            $decoded->standardFields()[0]->rawField,
        );

        self::assertSame(
            'Watch7,5',
            $this->validScalar(
                $decoded->standardFields()[0]->value,
            ),
        );
    }

    public function testCanonicalizesDifferentDuplicateStandardFieldPayloadsByFirstOccurrence(): void
    {
        $firstDefinition = StandardFieldDefinition::create(
            fieldNumber: 13,
            size: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
        );

        $secondDefinition = StandardFieldDefinition::create(
            fieldNumber: 13,
            size: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 19,
            standardFields: [
                $firstDefinition,
                $secondDefinition,
            ],
        );

        $firstField = new RawStandardField(
            definition: $firstDefinition,
            value: RawFieldValue::fromBytes(
                "\xAD\x0B",
            ),
        );

        $secondField = new RawStandardField(
            definition: $secondDefinition,
            value: RawFieldValue::fromBytes(
                "\xC1\x0B",
            ),
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 372,
            byteOffset: 14_150,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [
                $firstField,
                $secondField,
            ],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertCount(
            1,
            $decoded->standardFields(),
        );

        self::assertSame(
            [
                $firstField,
                $secondField,
            ],
            $decoded->source->standardFields(),
        );

        self::assertSame(
            $firstDefinition,
            $decoded->standardFields()[0]->definition,
        );

        self::assertSame(
            $firstField,
            $decoded->standardFields()[0]->rawField,
        );

        self::assertSame(
            2989,
            $this->validScalar(
                $decoded->standardFields()[0]->value,
            ),
        );
    }

    public function testCanonicalizesConflictingDuplicateStandardFieldsByFirstOccurrence(): void
    {
        $firstDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $secondDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
        );

        $followingDefinition = StandardFieldDefinition::create(
            fieldNumber: 4,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $firstDefinition,
                $secondDefinition,
                $followingDefinition,
            ],
        );

        $firstField = new RawStandardField(
            definition: $firstDefinition,
            value: RawFieldValue::fromBytes(
                "\x7F",
            ),
        );

        $secondField = new RawStandardField(
            definition: $secondDefinition,
            value: RawFieldValue::fromBytes(
                "\x34\x12",
            ),
        );

        $followingField = new RawStandardField(
            definition: $followingDefinition,
            value: RawFieldValue::fromBytes(
                "\xAA",
            ),
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [
                $firstField,
                $secondField,
                $followingField,
            ],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertCount(
            2,
            $decoded->standardFields(),
        );

        self::assertSame(
            $firstDefinition,
            $decoded->standardFields()[0]->definition,
        );

        self::assertSame(
            127,
            $this->validScalar(
                $decoded->standardFields()[0]->value,
            ),
        );

        self::assertSame(
            $followingDefinition,
            $decoded->standardFields()[1]->definition,
        );

        self::assertSame(
            170,
            $this->validScalar(
                $decoded->standardFields()[1]->value,
            ),
        );
    }

    private function timestampDefinition(): StandardFieldDefinition
    {
        return StandardFieldDefinition::create(
            fieldNumber: 253,
            size: 4,
            baseType: FitBaseType::fromDefinitionByte(
                0x86,
            ),
        );
    }

    private function validScalar(
        mixed $value,
    ): int|float|string {
        self::assertInstanceOf(
            DecodedFieldElements::class,
            $value,
        );

        $element = $value->elements()[0];

        self::assertInstanceOf(
            ValidFieldElement::class,
            $element,
        );

        return $element->value;
    }

    public function testPrependsCompressedHeaderTimestampBeforePayloadFields(): void
    {
        $heartRateDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 1,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [$heartRateDefinition],
        );

        $heartRateField = new RawStandardField(
            definition: $heartRateDefinition,
            value: RawFieldValue::fromBytes(
                "\x96",
            ),
        );

        $raw = RawCompressedTimestampDataMessage::create(
            sequenceNumber: 3,
            byteOffset: 23,
            recordHeaderByte: 0xAD,
            definition: $definition,
            reconstructedTimestamp: 1005,
            standardFields: [$heartRateField],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertCount(
            2,
            $decoded->standardFields(),
        );

        $timestamp = $decoded->standardFields()[0];

        self::assertSame(
            253,
            $timestamp->definition->fieldNumber,
        );

        self::assertSame(
            DecodedFieldOrigin::CompressedTimestampHeader,
            $timestamp->origin,
        );

        self::assertNull(
            $timestamp->rawField,
        );

        self::assertSame(
            1005,
            $this->validScalar($timestamp->value),
        );

        self::assertSame(
            DecodedFieldOrigin::MessagePayload,
            $decoded->standardFields()[1]->origin,
        );

        self::assertSame(
            150,
            $this->validScalar(
                $decoded->standardFields()[1]->value,
            ),
        );

        self::assertSame(
            $timestamp,
            $decoded->standardField(253),
        );
    }

    public function testCompressedPayloadTimestampIsConsumedButHeaderTimestampWinsSemanticLookup(): void
    {
        $timestampDefinition = $this->timestampDefinition();

        $heartRateDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 1,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $timestampDefinition,
                $heartRateDefinition,
            ],
        );

        $timestampField = new RawStandardField(
            definition: $timestampDefinition,
            value: RawFieldValue::fromBytes(
                "\xD0\x07\x00\x00",
            ),
        );

        $heartRateField = new RawStandardField(
            definition: $heartRateDefinition,
            value: RawFieldValue::fromBytes(
                "\x96",
            ),
        );

        $raw = RawCompressedTimestampDataMessage::create(
            sequenceNumber: 3,
            byteOffset: 26,
            recordHeaderByte: 0xAD,
            definition: $definition,
            reconstructedTimestamp: 1005,
            standardFields: [
                $timestampField,
                $heartRateField,
            ],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertCount(
            2,
            $decoded->standardFields(),
        );

        $timestamp = $decoded->standardFields()[0];

        self::assertSame(
            253,
            $timestamp->definition->fieldNumber,
        );

        self::assertSame(
            DecodedFieldOrigin::CompressedTimestampHeader,
            $timestamp->origin,
        );

        self::assertNull(
            $timestamp->rawField,
        );

        self::assertSame(
            1005,
            $this->validScalar($timestamp->value),
        );

        self::assertSame(
            3,
            $decoded->standardFields()[1]->definition->fieldNumber,
        );

        self::assertSame(
            150,
            $this->validScalar(
                $decoded->standardFields()[1]->value,
            ),
        );

        self::assertSame(
            $timestamp,
            $decoded->standardField(253),
        );
    }

    public function testPreservesInvalidElement(): void
    {
        $heartRateDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [$heartRateDefinition],
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [
                new RawStandardField(
                    definition: $heartRateDefinition,
                    value: RawFieldValue::fromBytes(
                        "\xFF",
                    ),
                ),
            ],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        $value = $decoded->standardFields()[0]->value;

        self::assertInstanceOf(
            DecodedFieldElements::class,
            $value,
        );

        self::assertInstanceOf(
            InvalidFieldElement::class,
            $value->elements()[0],
        );
    }

    public function testFindsDecodedFieldByNumber(): void
    {
        $definition = $this->timestampDefinition();

        $messageDefinition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [$definition],
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $messageDefinition,
            standardFields: [
                new RawStandardField(
                    definition: $definition,
                    value: RawFieldValue::fromBytes(
                        "\xE8\x03\x00\x00",
                    ),
                ),
            ],
        );

        $decoded = (new FitDataMessageDecoder())->decode(
            $raw,
        );

        self::assertSame(
            $decoded->standardFields()[0],
            $decoded->standardField(253),
        );

        self::assertNull(
            $decoded->standardField(200),
        );
    }

    public function testDecodesStreamLazilyAndSkipsDefinitions(): void
    {
        $fieldDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [$fieldDefinition],
        );

        $definitionMessage = new RawDefinitionMessage(
            sequenceNumber: 0,
            messageByteOffset: 0,
            recordHeaderByte: 0x40,
            reservedByte: 0,
            definition: $definition,
        );

        $dataMessage = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 9,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [
                new RawStandardField(
                    definition: $fieldDefinition,
                    value: RawFieldValue::fromBytes(
                        "\x96",
                    ),
                ),
            ],
        );

        $log = [];

        $rawStream = (
            static function () use (
                &$log,
                $definitionMessage,
                $dataMessage,
            ): iterable {
                $log[] = 'definition';

                yield 0 => $definitionMessage;

                $log[] = 'data';

                yield 1 => $dataMessage;
            }
        )();

        $decodedStream = (
        new FitDataMessageDecoder()
        )->decodeStream($rawStream);

        self::assertSame(
            [],
            $log,
        );

        $decoded = iterator_to_array(
            $decodedStream,
        );

        self::assertSame(
            [
                'definition',
                'data',
            ],
            $log,
        );

        self::assertSame(
            [1],
            array_keys($decoded),
        );

        self::assertSame(
            150,
            $this->validScalar(
                $decoded[1]->standardFields()[0]->value,
            ),
        );
    }
}
