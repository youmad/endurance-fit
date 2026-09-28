<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Developer;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Developer\DeveloperFieldProfile;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Raw\FitBaseType;

final class DeveloperFieldProfileTest extends TestCase
{
    public function testKeepsNativeReferenceSeparateFromDeveloperMetadata(): void
    {
        $native = FieldProfile::create(
            fieldNumber: 39,
            name: 'vertical_oscillation',
            typeName: 'uint16',
            units: 'mm',
        );

        $profile = DeveloperFieldProfile::create(
            developerDataIndex: 0,
            fieldDefinitionNumber: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
            name: 'custom_vertical_oscillation',
            transform: FieldTransform::scaleAndOffset(
                10,
            ),
            nativeMessageNumber: 20,
            nativeFieldNumber: 39,
            nativeField: $native,
        );

        self::assertSame(
            'uint16',
            $profile->typeName(),
        );

        self::assertSame($native, $profile->nativeField);
        self::assertNull($profile->effectiveUnits());

        self::assertSame(
            10.0,
            $profile->transform->scale,
        );
    }

    public function testExplicitUnitsOverrideNativeUnits(): void
    {
        $profile = DeveloperFieldProfile::create(
            developerDataIndex: 0,
            fieldDefinitionNumber: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
            name: 'custom_distance',
            units: 'cm',
            nativeMessageNumber: 20,
            nativeFieldNumber: 5,
            nativeField: FieldProfile::create(
                fieldNumber: 5,
                name: 'distance',
                typeName: 'uint32',
                units: 'm',
            ),
        );

        self::assertSame(
            'cm',
            $profile->effectiveUnits(),
        );
    }

    public function testPreservesOpaqueUnitsText(): void
    {
        foreach (['', ' '] as $units) {
            $profile = DeveloperFieldProfile::create(
                developerDataIndex: 0,
                fieldDefinitionNumber: 1,
                baseType: FitBaseType::fromDefinitionByte(
                    0x84,
                ),
                name: 'custom_distance',
                units: $units,
            );

            self::assertSame(
                $units,
                $profile->effectiveUnits(),
            );
        }
    }

    public function testAcceptsUnsigned16BitBaseUnitId(): void
    {
        $profile = DeveloperFieldProfile::create(
            developerDataIndex: 0,
            fieldDefinitionNumber: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
            name: 'custom_distance',
            fitBaseUnitId: 0x1234,
        );

        self::assertSame(0x1234, $profile->fitBaseUnitId);
    }

    public function testRejectsBaseUnitIdWiderThanUnsigned16Bits(): void
    {
        $this->expectException(InvalidFitProfile::class);
        $this->expectExceptionMessage(
            'unsigned 16-bit integer',
        );

        DeveloperFieldProfile::create(
            developerDataIndex: 0,
            fieldDefinitionNumber: 1,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
            name: 'custom_distance',
            fitBaseUnitId: 0x10000,
        );
    }
}
