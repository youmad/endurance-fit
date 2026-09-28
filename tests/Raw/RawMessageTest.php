<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Raw;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;
use Youmad\Endurance\Fit\Raw\DeveloperFieldDefinition;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawDefinitionMessage;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawFitMessage;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final class RawMessageTest extends TestCase
{
    public function testPreservesDefinitionAndDataMessagesInFileOrder(): void
    {
        $timestampDefinition = StandardFieldDefinition::create(
            fieldNumber: 253,
            size: 4,
            baseType: FitBaseType::fromDefinitionByte(
                0x86,
            ),
        );

        $unknownDefinition = StandardFieldDefinition::create(
            fieldNumber: 200,
            size: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x1F,
            ),
        );

        $developerDefinition = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 1,
            developerDataIndex: 3,
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 4,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 65_000,
            standardFields: [
                $timestampDefinition,
                $unknownDefinition,
            ],
            developerFields: [
                $developerDefinition,
            ],
        );

        $definitionMessage = new RawDefinitionMessage(
            sequenceNumber: 0,
            messageByteOffset: 14,
            recordHeaderByte: 0x64,
            reservedByte: 0,
            definition: $definition,
        );

        $timestampField = new RawStandardField(
            definition: $timestampDefinition,
            value: RawFieldValue::fromBytes(
                "\x01\x02\x03\x04",
            ),
        );

        $unknownField = new RawStandardField(
            definition: $unknownDefinition,
            value: RawFieldValue::fromBytes(
                "\xAA\xBB",
            ),
        );

        $developerField = new RawDeveloperField(
            definition: $developerDefinition,
            value: RawFieldValue::fromBytes(
                "\xFF",
            ),
        );

        $dataMessage = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 29,
            recordHeaderByte: 0x04,
            definition: $definition,
            standardFields: [
                $timestampField,
                $unknownField,
            ],
            developerFields: [
                $developerField,
            ],
        );

        /** @var list<RawFitMessage> $stream */
        $stream = [
            $definitionMessage,
            $dataMessage,
        ];

        self::assertSame(
            0,
            $stream[0]->sequence(),
        );

        self::assertSame(
            14,
            $stream[0]->byteOffset(),
        );

        self::assertSame(
            1,
            $stream[1]->sequence(),
        );

        self::assertSame(
            29,
            $stream[1]->byteOffset(),
        );

        self::assertSame(
            0x64,
            $definitionMessage->recordHeaderByte(),
        );

        self::assertSame(
            0x04,
            $dataMessage->recordHeaderByte(),
        );

        self::assertSame(
            65_000,
            $dataMessage->globalMessageNumber(),
        );

        self::assertSame(
            "\xAA\xBB",
            $dataMessage
                ->standardFields()[1]
                ->value
                ->bytes(),
        );

        self::assertSame(
            "\xFF",
            $dataMessage
                ->developerFields()[0]
                ->value
                ->bytes(),
        );
    }

    public function testStandardFieldValueMustMatchDefinedSize(): void
    {
        $definition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        new RawStandardField(
            definition: $definition,
            value: RawFieldValue::fromBytes(
                "\x01",
            ),
        );
    }

    public function testDeveloperFieldValueMustMatchDefinedSize(): void
    {
        $definition = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 2,
            developerDataIndex: 1,
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        new RawDeveloperField(
            definition: $definition,
            value: RawFieldValue::fromBytes(
                "\x01",
            ),
        );
    }

    public function testDataMessageMustContainEveryStandardField(): void
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

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 10,
            recordHeaderByte: 0x00,
            definition: $definition,
        );
    }

    public function testStandardFieldsMustFollowDefinitionOrder(): void
    {
        $firstDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $secondDefinition = StandardFieldDefinition::create(
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
            ],
        );

        $firstField = new RawStandardField(
            definition: $firstDefinition,
            value: RawFieldValue::fromBytes("\x01"),
        );

        $secondField = new RawStandardField(
            definition: $secondDefinition,
            value: RawFieldValue::fromBytes("\x02"),
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 10,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [
                $secondField,
                $firstField,
            ],
        );
    }

    public function testFieldMustBelongToExactActiveDefinition(): void
    {
        $activeFieldDefinition = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $equivalentButInactiveDefinition = StandardFieldDefinition::create(
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
            standardFields: [$activeFieldDefinition],
        );

        $field = new RawStandardField(
            definition: $equivalentButInactiveDefinition,
            value: RawFieldValue::fromBytes("\x01"),
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 10,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: [$field],
        );
    }

    public function testMessageSequenceCannotBeNegative(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        new RawDefinitionMessage(
            sequenceNumber: -1,
            messageByteOffset: 0,
            recordHeaderByte: 0x40,
            reservedByte: 0,
            definition: $definition,
        );
    }

    public function testDefinitionHeaderMustMatchLocalMessageNumber(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 2,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        new RawDefinitionMessage(
            sequenceNumber: 0,
            messageByteOffset: 0,
            recordHeaderByte: 0x41,
            reservedByte: 0,
            definition: $definition,
        );
    }

    public function testDataHeaderMustMatchLocalMessageNumber(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 2,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 10,
            recordHeaderByte: 0x01,
            definition: $definition,
        );
    }
}
