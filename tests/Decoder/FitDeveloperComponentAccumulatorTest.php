<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\FitDeveloperComponentAccumulator;

final class FitDeveloperComponentAccumulatorTest extends TestCase
{
    public function testAccumulatesWithModuloRollover(): void
    {
        $accumulator = new FitDeveloperComponentAccumulator();

        self::assertSame(
            250,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                developerDataIndex: 0,
                fieldDefinitionNumber: 7,
                componentIndex: 1,
                packedValue: 250,
                bits: 8,
            ),
        );

        self::assertSame(
            259,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                developerDataIndex: 0,
                fieldDefinitionNumber: 7,
                componentIndex: 1,
                packedValue: 3,
                bits: 8,
            ),
        );
    }

    public function testUsesCurrentBitWidthForEachOccurrence(): void
    {
        $accumulator = new FitDeveloperComponentAccumulator();

        self::assertSame(
            4090,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                developerDataIndex: 0,
                fieldDefinitionNumber: 7,
                componentIndex: 1,
                packedValue: 4090,
                bits: 12,
            ),
        );

        self::assertSame(
            4099,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                developerDataIndex: 0,
                fieldDefinitionNumber: 7,
                componentIndex: 1,
                packedValue: 3,
                bits: 8,
            ),
        );
    }

    public function testDifferentComponentIdentitiesHaveIndependentState(): void
    {
        $accumulator = new FitDeveloperComponentAccumulator();

        self::assertSame(
            250,
            $accumulator->accumulate(20, 0, 7, 0, 250, 8),
        );
        self::assertSame(
            3,
            $accumulator->accumulate(20, 0, 7, 1, 3, 8),
        );
        self::assertSame(
            259,
            $accumulator->accumulate(20, 0, 7, 0, 3, 8),
        );
    }
}
