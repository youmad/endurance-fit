<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidFitDefinition;

final readonly class StandardFieldDefinition
{
    private const int MINIMUM_FIELD_NUMBER = 0;
    private const int MAXIMUM_FIELD_NUMBER = 255;
    private const int MINIMUM_SIZE = 0;
    private const int MAXIMUM_SIZE = 255;

    private function __construct(
        public int $fieldNumber,
        public int $size,
        public FitBaseType $baseType,
    ) {
    }

    public static function create(
        int $fieldNumber,
        int $size,
        FitBaseType $baseType,
    ): self {
        self::assertByte(
            name: 'Standard FIT field number',
            value: $fieldNumber,
            minimum: self::MINIMUM_FIELD_NUMBER,
            maximum: self::MAXIMUM_FIELD_NUMBER,
        );

        self::assertByte(
            name: 'Standard FIT field size',
            value: $size,
            minimum: self::MINIMUM_SIZE,
            maximum: self::MAXIMUM_SIZE,
        );

        return new self(
            fieldNumber: $fieldNumber,
            size: $size,
            baseType: $baseType,
        );
    }

    private static function assertByte(
        string $name,
        int $value,
        int $minimum,
        int $maximum,
    ): void {
        if (
            $minimum > $value
            || $maximum < $value
        ) {
            throw new InvalidFitDefinition(sprintf('%s must be between %d and %d.', $name, $minimum, $maximum));
        }
    }
}
