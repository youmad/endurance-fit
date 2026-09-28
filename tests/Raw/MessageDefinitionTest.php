<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Raw;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\InvalidFitDefinition;
use Youmad\Endurance\Fit\Raw\DeveloperFieldDefinition;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final class MessageDefinitionTest extends TestCase
{
    public function testPreservesDefinitionWithoutKnowingItsProfileMessage(): void
    {
        $baseType = FitBaseType::fromDefinitionByte(
            0x1F,
        );

        $standardField = StandardFieldDefinition::create(
            fieldNumber: 200,
            size: 4,
            baseType: $baseType,
        );

        $developerField = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 2,
            developerDataIndex: 3,
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 15,
            architecture: FitArchitecture::BigEndian,
            globalMessageNumber: 65_000,
            standardFields: [$standardField],
            developerFields: [$developerField],
        );

        self::assertSame(
            15,
            $definition->localMessageNumber,
        );

        self::assertSame(
            FitArchitecture::BigEndian,
            $definition->architecture,
        );

        self::assertSame(
            65_000,
            $definition->globalMessageNumber,
        );

        self::assertSame(
            0x1F,
            $baseType->definitionByte(),
        );

        self::assertSame(
            0x1F,
            $baseType->number(),
        );

        self::assertFalse(
            $baseType->isKnown(),
        );

        self::assertSame(
            [$standardField],
            $definition->standardFields(),
        );

        self::assertSame(
            [$developerField],
            $definition->developerFields(),
        );
    }

    public function testSeparatesDefinitionByteFromBaseTypeNumber(): void
    {
        $baseType = FitBaseType::fromDefinitionByte(
            0x86,
        );

        self::assertSame(
            0x86,
            $baseType->definitionByte(),
        );

        self::assertSame(
            0x06,
            $baseType->number(),
        );

        self::assertTrue(
            $baseType->isKnown(),
        );
    }

    public function testDefinitionMayHaveNoFields(): void
    {
        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
        );

        self::assertSame(
            [],
            $definition->standardFields(),
        );

        self::assertSame(
            [],
            $definition->developerFields(),
        );
    }

    public function testLocalMessageNumberCannotExceedFourBits(): void
    {
        $this->expectException(
            InvalidFitDefinition::class,
        );

        MessageDefinition::create(
            localMessageNumber: 16,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
        );
    }

    public function testGlobalMessageNumberMustFitUnsignedSixteenBits(): void
    {
        $this->expectException(
            InvalidFitDefinition::class,
        );

        MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 65_536,
        );
    }

    public function testBaseTypeDefinitionMustFitIntoOneByte(): void
    {
        $this->expectException(
            InvalidFitDefinition::class,
        );

        FitBaseType::fromDefinitionByte(256);
    }

    public function testZeroSizedStandardFieldIsPreservedButConsumesNoPayload(): void
    {
        $field = StandardFieldDefinition::create(
            fieldNumber: 0,
            size: 0,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [$field],
        );

        self::assertSame(
            [$field],
            $definition->standardFields(),
        );

        self::assertSame(
            [],
            $definition->payloadStandardFields(),
        );

        self::assertSame(
            [],
            $definition->canonicalStandardFields(),
        );

        self::assertSame(
            [],
            $definition->canonicalStandardFieldsByPayloadIndex(),
        );
    }

    public function testZeroSizedDeveloperFieldIsPreservedButConsumesNoPayload(): void
    {
        $field = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 0,
            developerDataIndex: 3,
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            developerFields: [$field],
        );

        self::assertSame(
            [$field],
            $definition->developerFields(),
        );

        self::assertSame(
            [],
            $definition->payloadDeveloperFields(),
        );
    }

    public function testStandardFieldSizeCannotBeNegative(): void
    {
        $this->expectException(
            InvalidFitDefinition::class,
        );

        StandardFieldDefinition::create(
            fieldNumber: 0,
            size: -1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );
    }

    public function testDeveloperFieldSizeCannotBeNegative(): void
    {
        $this->expectException(
            InvalidFitDefinition::class,
        );

        DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: -1,
            developerDataIndex: 3,
        );
    }

    public function testCompilesPayloadAndCanonicalStandardFieldPlan(): void
    {
        $zeroSized = StandardFieldDefinition::create(
            fieldNumber: 1,
            size: 0,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $firstHeartRate = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $altitude = StandardFieldDefinition::create(
            fieldNumber: 2,
            size: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
        );

        $duplicateHeartRate = StandardFieldDefinition::create(
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
                $zeroSized,
                $firstHeartRate,
                $altitude,
                $duplicateHeartRate,
            ],
        );

        self::assertSame(
            [
                $firstHeartRate,
                $altitude,
                $duplicateHeartRate,
            ],
            $definition->payloadStandardFields(),
        );

        self::assertSame(
            [
                $firstHeartRate,
                $altitude,
            ],
            $definition->canonicalStandardFields(),
        );

        self::assertSame(
            [
                0 => $firstHeartRate,
                1 => $altitude,
            ],
            $definition->canonicalStandardFieldsByPayloadIndex(),
        );
    }

    public function testIdenticalDuplicateStandardFieldsArePreservedAndCanonicalized(): void
    {
        $first = StandardFieldDefinition::create(
            fieldNumber: 27,
            size: 9,
            baseType: FitBaseType::fromDefinitionByte(
                0x07,
            ),
        );

        $second = StandardFieldDefinition::create(
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
                $first,
                $second,
            ],
        );

        self::assertSame(
            [
                $first,
                $second,
            ],
            $definition->standardFields(),
        );

        self::assertSame(
            [$first],
            $definition->canonicalStandardFields(),
        );
    }

    public function testConflictingDuplicateStandardFieldsArePreservedAndCanonicalizedByFirstOccurrence(): void
    {
        $first = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x02,
            ),
        );

        $second = StandardFieldDefinition::create(
            fieldNumber: 3,
            size: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            standardFields: [
                $first,
                $second,
            ],
        );

        self::assertSame(
            [
                $first,
                $second,
            ],
            $definition->standardFields(),
        );

        self::assertSame(
            [$first],
            $definition->canonicalStandardFields(),
        );
    }

    public function testDeveloperFieldIdentityIncludesDeveloperIndex(): void
    {
        $first = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 2,
            developerDataIndex: 1,
        );

        $second = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 2,
            developerDataIndex: 2,
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            developerFields: [
                $first,
                $second,
            ],
        );

        self::assertSame(
            [
                $first,
                $second,
            ],
            $definition->developerFields(),
        );
    }

    public function testDuplicateDeveloperFieldIdentityIsPreservedInWireOrder(): void
    {
        $first = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 2,
            developerDataIndex: 1,
        );

        $second = DeveloperFieldDefinition::create(
            fieldNumber: 7,
            size: 4,
            developerDataIndex: 1,
        );

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 20,
            developerFields: [
                $first,
                $second,
            ],
        );

        self::assertSame(
            [
                $first,
                $second,
            ],
            $definition->developerFields(),
        );
    }
}
