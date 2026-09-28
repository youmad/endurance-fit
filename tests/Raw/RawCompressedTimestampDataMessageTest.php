<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Raw;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawCompressedTimestampDataMessage;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final class RawCompressedTimestampDataMessageTest extends TestCase
{
    public function testPayloadFollowsActiveDefinitionEvenWhenItContainsTimestamp(): void
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
                "\x7F",
            ),
        );

        $message = RawCompressedTimestampDataMessage::create(
            sequenceNumber: 3,
            byteOffset: 23,
            recordHeaderByte: 0xAD,
            definition: $definition,
            reconstructedTimestamp: 1005,
            standardFields: [
                $timestampField,
                $heartRateField,
            ],
        );

        self::assertSame(
            13,
            $message->timeOffset,
        );

        self::assertSame(
            1005,
            $message->reconstructedTimestamp,
        );

        self::assertSame(
            [
                $timestampField,
                $heartRateField,
            ],
            $message->standardFields(),
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

    public function testRequiresTimestampPayloadWhenDefinitionContainsIt(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 1,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $this->timestampDefinition(),
            ],
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawCompressedTimestampDataMessage::create(
            sequenceNumber: 0,
            byteOffset: 0,
            recordHeaderByte: 0xAD,
            definition: $definition,
            reconstructedTimestamp: 1005,
        );
    }

    public function testDefinitionMayOmitTimestampField(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 1,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
        );

        $message = RawCompressedTimestampDataMessage::create(
            sequenceNumber: 0,
            byteOffset: 0,
            recordHeaderByte: 0xA0,
            definition: $definition,
            reconstructedTimestamp: 1000,
        );

        self::assertSame(
            1000,
            $message->reconstructedTimestamp,
        );

        self::assertSame(
            [],
            $message->standardFields(),
        );
    }

    public function testRequiresCompressedRecordHeader(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 1,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $this->timestampDefinition(),
            ],
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawCompressedTimestampDataMessage::create(
            sequenceNumber: 0,
            byteOffset: 0,
            recordHeaderByte: 0x01,
            definition: $definition,
            reconstructedTimestamp: 1000,
        );
    }

    public function testHeaderMustMatchLocalMessageNumber(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 2,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $this->timestampDefinition(),
            ],
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawCompressedTimestampDataMessage::create(
            sequenceNumber: 0,
            byteOffset: 0,
            recordHeaderByte: 0xA0,
            definition: $definition,
            reconstructedTimestamp: 1000,
        );
    }

    public function testReconstructedTimestampMustFitUnsignedThirtyTwoBits(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 1,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $this->timestampDefinition(),
            ],
        );

        $this->expectException(
            InvalidRawFitMessage::class,
        );

        RawCompressedTimestampDataMessage::create(
            sequenceNumber: 0,
            byteOffset: 0,
            recordHeaderByte: 0xA0,
            definition: $definition,
            reconstructedTimestamp: 0x100000000,
        );
    }
}
