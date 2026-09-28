<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Developer;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Developer\DeveloperDataProfile;
use Youmad\Endurance\Fit\Developer\DeveloperFieldProfile;
use Youmad\Endurance\Fit\Developer\FitDeveloperProfileRegistry;
use Youmad\Endurance\Fit\Raw\FitBaseType;

final class FitDeveloperProfileRegistryTest extends TestCase
{
    public function testRegistersProfilesIdempotently(): void
    {
        $registry = new FitDeveloperProfileRegistry();

        $developer = DeveloperDataProfile::create(
            developerDataIndex: 0,
            applicationVersion: 1,
        );

        $field = DeveloperFieldProfile::create(
            developerDataIndex: 0,
            fieldDefinitionNumber: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
            name: 'custom_power',
        );

        $registry->registerDeveloperData($developer);
        $registry->registerDeveloperData($developer);
        $registry->registerField($field);
        $registry->registerField($field);

        self::assertSame(
            $developer,
            $registry->developerData(0),
        );

        self::assertSame(
            $field,
            $registry->field(0, 2),
        );
    }

    public function testLatestFieldDescriptionReplacesEarlierOne(): void
    {
        $registry = new FitDeveloperProfileRegistry();

        $registry->registerDeveloperData(
            DeveloperDataProfile::create(
                developerDataIndex: 0,
            ),
        );

        $registry->registerField(
            DeveloperFieldProfile::create(
                developerDataIndex: 0,
                fieldDefinitionNumber: 2,
                baseType: FitBaseType::fromDefinitionByte(
                    0x84,
                ),
                name: 'custom_power',
            ),
        );

        $replacement = DeveloperFieldProfile::create(
            developerDataIndex: 0,
            fieldDefinitionNumber: 2,
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
            name: 'different_name',
        );

        $registry->registerField($replacement);

        self::assertSame(
            $replacement,
            $registry->field(0, 2),
        );
    }

    public function testIgnoresFieldDescriptionUntilDeveloperDataIsRegistered(): void
    {
        $registry = new FitDeveloperProfileRegistry();

        $registry->registerField(
            DeveloperFieldProfile::create(
                developerDataIndex: 0,
                fieldDefinitionNumber: 2,
                baseType: FitBaseType::fromDefinitionByte(
                    0x84,
                ),
                name: 'custom_power',
            ),
        );

        $registry->registerDeveloperData(
            DeveloperDataProfile::create(
                developerDataIndex: 0,
            ),
        );

        self::assertNull(
            $registry->field(0, 2),
        );
    }

    public function testReregisteringDeveloperDataReplacesMetadataAndClearsFields(): void
    {
        $registry = new FitDeveloperProfileRegistry();

        $registry->registerDeveloperData(
            DeveloperDataProfile::create(
                developerDataIndex: 0,
                applicationVersion: 1,
            ),
        );

        $registry->registerField(
            DeveloperFieldProfile::create(
                developerDataIndex: 0,
                fieldDefinitionNumber: 2,
                baseType: FitBaseType::fromDefinitionByte(
                    0x84,
                ),
                name: 'custom_power',
            ),
        );

        $replacement = DeveloperDataProfile::create(
            developerDataIndex: 0,
            applicationVersion: 2,
        );

        $registry->registerDeveloperData($replacement);

        self::assertSame(
            $replacement,
            $registry->developerData(0),
        );
        self::assertNull(
            $registry->field(0, 2),
        );
    }

    public function testResetRemovesFileScopedProfiles(): void
    {
        $registry = new FitDeveloperProfileRegistry();

        $registry->registerDeveloperData(
            DeveloperDataProfile::create(
                developerDataIndex: 0,
            ),
        );

        $registry->reset();

        self::assertNull(
            $registry->developerData(0),
        );
    }
}
