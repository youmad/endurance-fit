<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidFitFile;

final readonly class FitFileTrailer
{
    private const int MINIMUM_CRC = 0;
    private const int MAXIMUM_CRC = 65_535;

    public function __construct(
        public int $byteOffset,
        public int $declaredCrc,
        public int $calculatedCrc,
    ) {
        if (0 > $byteOffset) {
            throw new InvalidFitFile('FIT file trailer offset cannot be negative.');
        }

        self::assertCrc(
            'Declared FIT file CRC',
            $declaredCrc,
        );

        self::assertCrc(
            'Calculated FIT file CRC',
            $calculatedCrc,
        );

        if ($declaredCrc !== $calculatedCrc) {
            throw new InvalidFitFile(sprintf('FIT file CRC mismatch: declared 0x%04X, calculated 0x%04X.', $declaredCrc, $calculatedCrc));
        }
    }

    private static function assertCrc(
        string $name,
        int $value,
    ): void {
        if (
            self::MINIMUM_CRC > $value
            || self::MAXIMUM_CRC < $value
        ) {
            throw new InvalidFitFile(sprintf('%s must fit into an unsigned 16-bit integer.', $name));
        }
    }
}
