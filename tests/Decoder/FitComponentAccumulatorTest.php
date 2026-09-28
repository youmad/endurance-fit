<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\FitComponentAccumulator;

final class FitComponentAccumulatorTest extends TestCase
{
    public function testFirstValueBecomesInitialAccumulatedValue(): void
    {
        $accumulator = new FitComponentAccumulator();

        self::assertSame(
            100,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 100,
                bits: 12,
            ),
        );

        self::assertTrue(
            $accumulator->has(
                globalMessageNumber: 20,
                fieldNumber: 5,
            ),
        );
    }

    public function testAccumulatesNormalIncrease(): void
    {
        $accumulator = new FitComponentAccumulator();

        $accumulator->accumulate(
            globalMessageNumber: 20,
            fieldNumber: 5,
            packedValue: 100,
            bits: 12,
        );

        self::assertSame(
            110,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 110,
                bits: 12,
            ),
        );
    }

    public function testAccountsForRollover(): void
    {
        $accumulator = new FitComponentAccumulator();

        self::assertSame(
            4090,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 4090,
                bits: 12,
            ),
        );

        self::assertSame(
            4099,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 3,
                bits: 12,
            ),
        );

        self::assertSame(
            4106,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 10,
                bits: 12,
            ),
        );
    }

    public function testFullValueMaySeedPackedStream(): void
    {
        $accumulator = new FitComponentAccumulator();

        $accumulator->seed(
            globalMessageNumber: 20,
            fieldNumber: 5,
            value: 25_600,
        );

        self::assertSame(
            25_616,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 1040,
                bits: 12,
            ),
        );
    }

    public function testUsesCurrentBitWidthForEveryOccurrence(): void
    {
        $accumulator = new FitComponentAccumulator();

        self::assertSame(
            4090,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 4090,
                bits: 12,
            ),
        );

        self::assertSame(
            4099,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 3,
                bits: 8,
            ),
        );
    }

    public function testAppliesBitMaskToSignedPackedValues(): void
    {
        $accumulator = new FitComponentAccumulator();

        self::assertSame(
            254,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: -2,
                bits: 8,
            ),
        );

        self::assertSame(
            255,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: -1,
                bits: 8,
            ),
        );
    }

    public function testAppliesBitMaskInsteadOfRejectingWiderPackedValue(): void
    {
        $accumulator = new FitComponentAccumulator();

        self::assertSame(
            0,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 256,
                bits: 8,
            ),
        );
    }

    public function testResetClearsAccumulatedState(): void
    {
        $accumulator = new FitComponentAccumulator();

        $accumulator->accumulate(
            globalMessageNumber: 20,
            fieldNumber: 5,
            packedValue: 4090,
            bits: 12,
        );

        $accumulator->reset();

        self::assertFalse(
            $accumulator->has(
                globalMessageNumber: 20,
                fieldNumber: 5,
            ),
        );

        self::assertSame(
            3,
            $accumulator->accumulate(
                globalMessageNumber: 20,
                fieldNumber: 5,
                packedValue: 3,
                bits: 12,
            ),
        );
    }
}
