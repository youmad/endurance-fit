<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class ComponentProfile
{
    private const int MINIMUM_FIELD_NUMBER = 0;
    private const int MAXIMUM_FIELD_NUMBER = 255;

    private const int MINIMUM_BITS = 1;
    private const int MAXIMUM_BITS = 63;

    private function __construct(
        public int $targetFieldNumber,
        public int $bits,
        public FieldTransform $transform,
        public ?string $units,
        public bool $accumulated,
        public bool $signed,
    ) {
    }

    public static function create(
        int $targetFieldNumber,
        int $bits,
        ?FieldTransform $transform = null,
        ?string $units = null,
        bool $accumulated = false,
        bool $signed = false,
    ): self {
        if (
            self::MINIMUM_FIELD_NUMBER > $targetFieldNumber
            || self::MAXIMUM_FIELD_NUMBER < $targetFieldNumber
        ) {
            throw new InvalidFitProfile('FIT component target field number must be between 0 and 255.');
        }

        if (
            self::MINIMUM_BITS > $bits
            || self::MAXIMUM_BITS < $bits
        ) {
            throw new InvalidFitProfile('FIT component size must be between 1 and 63 bits.');
        }

        if (
            null !== $units
            && (
                '' === $units
                || trim($units) !== $units
            )
        ) {
            throw new InvalidFitProfile('FIT component units must be a non-empty trimmed string.');
        }

        return new self(
            targetFieldNumber: $targetFieldNumber,
            bits: $bits,
            transform: $transform
            ?? FieldTransform::identity(),
            units: $units,
            accumulated: $accumulated,
            signed: $signed,
        );
    }
}
