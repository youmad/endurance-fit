<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidFitDefinition;

final readonly class DeveloperFieldDefinition
{
    private const int MINIMUM_BYTE_VALUE = 0;
    private const int MAXIMUM_BYTE_VALUE = 255;
    private const int MINIMUM_SIZE = 0;

    private function __construct(
        public int $fieldNumber,
        public int $size,
        public int $developerDataIndex,
    ) {
    }

    public static function create(
        int $fieldNumber,
        int $size,
        int $developerDataIndex,
    ): self {
        self::assertByte(
            name: 'Developer FIT field number',
            value: $fieldNumber,
            minimum: self::MINIMUM_BYTE_VALUE,
        );

        self::assertByte(
            name: 'Developer FIT field size',
            value: $size,
            minimum: self::MINIMUM_SIZE,
        );

        self::assertByte(
            name: 'FIT developer data index',
            value: $developerDataIndex,
            minimum: self::MINIMUM_BYTE_VALUE,
        );

        return new self(
            fieldNumber: $fieldNumber,
            size: $size,
            developerDataIndex: $developerDataIndex,
        );
    }

    private static function assertByte(
        string $name,
        int $value,
        int $minimum,
    ): void {
        if (
            $minimum > $value
            || self::MAXIMUM_BYTE_VALUE < $value
        ) {
            throw new InvalidFitDefinition(sprintf('%s must be between %d and %d.', $name, $minimum, self::MAXIMUM_BYTE_VALUE));
        }
    }
}
