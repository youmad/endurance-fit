<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Profile;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\InMemoryFitProfileRegistry;
use Youmad\Endurance\Fit\Profile\MessageProfile;

final class ProfileContractsTest extends TestCase
{
    public function testCreatesImmutableProfileRegistry(): void
    {
        $altitude = FieldProfile::create(
            fieldNumber: 2,
            name: 'altitude',
            typeName: 'uint16',
            transform: FieldTransform::scaleAndOffset(
                scale: 5,
                offset: 500,
            ),
            units: 'm',
        );

        $heartRate = FieldProfile::create(
            fieldNumber: 3,
            name: 'heart_rate',
            typeName: 'uint8',
            units: 'bpm',
        );

        $record = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                $altitude,
                $heartRate,
            ],
        );

        $registry = new InMemoryFitProfileRegistry(
            $record,
        );

        self::assertSame(
            $record,
            $registry->message(20),
        );

        self::assertSame(
            $altitude,
            $record->field(2),
        );

        self::assertSame(
            $heartRate,
            $record->field(3),
        );

        self::assertNull(
            $record->field(200),
        );

        self::assertNull(
            $registry->message(65_000),
        );
    }

    public function testIdentityTransformPreservesValue(): void
    {
        $transform = FieldTransform::identity();

        self::assertTrue(
            $transform->isIdentity(),
        );

        self::assertSame(
            150,
            $transform->apply(150),
        );
    }

    public function testAppliesScaleBeforeOffset(): void
    {
        $transform = FieldTransform::scaleAndOffset(
            scale: 5,
            offset: 500,
        );

        self::assertSame(
            1587,
            $transform->apply(10_435),
        );
    }

    public function testPreservesFractionalResult(): void
    {
        $transform = FieldTransform::scaleAndOffset(
            scale: 10,
        );

        self::assertSame(
            12.5,
            $transform->apply(125),
        );
    }

    public function testScaleCannotBeZero(): void
    {
        $this->expectException(
            InvalidFitProfile::class,
        );

        FieldTransform::scaleAndOffset(
            scale: 0,
        );
    }

    public function testFieldNameMustBeSnakeCase(): void
    {
        $this->expectException(
            InvalidFitProfile::class,
        );

        FieldProfile::create(
            fieldNumber: 3,
            name: 'heartRate',
            typeName: 'uint8',
        );
    }

    public function testFieldUnitsCannotHaveOuterWhitespace(): void
    {
        $this->expectException(
            InvalidFitProfile::class,
        );

        FieldProfile::create(
            fieldNumber: 3,
            name: 'heart_rate',
            typeName: 'uint8',
            units: ' bpm ',
        );
    }

    public function testMessageFieldNumbersMustBeUnique(): void
    {
        $first = FieldProfile::create(
            fieldNumber: 3,
            name: 'heart_rate',
            typeName: 'uint8',
        );

        $second = FieldProfile::create(
            fieldNumber: 3,
            name: 'other_heart_rate',
            typeName: 'uint8',
        );

        $this->expectException(
            InvalidFitProfile::class,
        );

        MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                $first,
                $second,
            ],
        );
    }

    public function testRegistryMessageNumbersMustBeUnique(): void
    {
        $first = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
        );

        $second = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'other_record',
        );

        $this->expectException(
            InvalidFitProfile::class,
        );

        new InMemoryFitProfileRegistry(
            $first,
            $second,
        );
    }
}
