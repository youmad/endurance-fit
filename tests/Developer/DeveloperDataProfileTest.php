<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Developer;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Developer\DeveloperDataProfile;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final class DeveloperDataProfileTest extends TestCase
{
    public function testCreatesDeveloperIdentity(): void
    {
        $profile = DeveloperDataProfile::create(
            developerDataIndex: 3,
            developerId: [1, 2, 3],
            applicationId: [4, 5, 6],
            manufacturerId: 1,
            applicationVersion: 42,
        );

        self::assertSame(
            3,
            $profile->developerDataIndex,
        );

        self::assertSame(
            [1, 2, 3],
            $profile->developerId(),
        );

        self::assertSame(
            [4, 5, 6],
            $profile->applicationId(),
        );

        self::assertSame(
            1,
            $profile->manufacturerId,
        );

        self::assertSame(
            42,
            $profile->applicationVersion,
        );
    }

    public function testRejectsInvalidByteArray(): void
    {
        $this->expectException(
            InvalidFitProfile::class,
        );

        DeveloperDataProfile::create(
            developerDataIndex: 0,
            applicationId: [0, 256],
        );
    }
}
