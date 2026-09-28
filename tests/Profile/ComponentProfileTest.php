<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Profile;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\SubfieldCondition;
use Youmad\Endurance\Fit\Profile\SubfieldProfile;

final class ComponentProfileTest extends TestCase
{
    public function testCreatesComponentProfile(): void
    {
        $transform = FieldTransform::scaleAndOffset(
            scale: 100,
        );

        $component = ComponentProfile::create(
            targetFieldNumber: 6,
            bits: 12,
            transform: $transform,
            units: 'm/s',
            accumulated: true,
            signed: true,
        );

        self::assertSame(
            6,
            $component->targetFieldNumber,
        );

        self::assertSame(
            12,
            $component->bits,
        );

        self::assertSame(
            $transform,
            $component->transform,
        );

        self::assertSame(
            'm/s',
            $component->units,
        );

        self::assertTrue($component->accumulated);
        self::assertTrue($component->signed);
    }

    public function testComponentMustContainAtLeastOneBit(): void
    {
        $this->expectException(
            InvalidFitProfile::class,
        );

        ComponentProfile::create(
            targetFieldNumber: 6,
            bits: 0,
        );
    }

    public function testFieldPreservesComponentOrderAndRepeatedTargets(): void
    {
        $first = ComponentProfile::create(
            targetFieldNumber: 9,
            bits: 4,
        );

        $second = ComponentProfile::create(
            targetFieldNumber: 9,
            bits: 4,
        );

        $field = FieldProfile::create(
            fieldNumber: 8,
            name: 'packed_values',
            typeName: 'byte',
            components: [
                $first,
                $second,
            ],
        );

        self::assertTrue(
            $field->hasComponents(),
        );

        self::assertSame(
            [
                $first,
                $second,
            ],
            $field->components(),
        );
    }

    public function testTargetFieldMayBeAccumulated(): void
    {
        $field = FieldProfile::create(
            fieldNumber: 5,
            name: 'distance',
            typeName: 'uint32',
            units: 'm',
            accumulated: true,
        );

        self::assertTrue(
            $field->isAccumulated(),
        );
    }

    public function testSubfieldMayDefineItsOwnComponents(): void
    {
        $component = ComponentProfile::create(
            targetFieldNumber: 9,
            bits: 8,
        );

        $subfield = SubfieldProfile::create(
            name: 'gear_change_data',
            typeName: 'uint32',
            conditions: [
                SubfieldCondition::create(
                    referenceFieldNumber: 0,
                    acceptedRawValues: [1],
                ),
            ],
            components: [$component],
        );

        self::assertTrue(
            $subfield->hasComponents(),
        );

        self::assertSame(
            [$component],
            $subfield->components(),
        );
    }

    public function testComponentsMustContainComponentProfiles(): void
    {
        $this->expectException(
            InvalidFitProfile::class,
        );

        FieldProfile::create(
            fieldNumber: 8,
            name: 'packed_values',
            typeName: 'byte',
            components: [
                'not-a-component',
            ],
        );
    }
}
