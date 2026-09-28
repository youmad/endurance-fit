<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class FieldTransform
{
    private const float INTEGER_TOLERANCE_MULTIPLIER = 8.0;

    private function __construct(
        public float $scale,
        public float $offset,
    ) {
    }

    public static function identity(): self
    {
        return new self(
            scale: 1.0,
            offset: 0.0,
        );
    }

    public static function scaleAndOffset(
        int|float $scale,
        int|float $offset = 0,
    ): self {
        $scale = (float) $scale;
        $offset = (float) $offset;

        if (
            !is_finite($scale)
            || 0.0 === $scale
        ) {
            throw new InvalidFitProfile('FIT field scale must be finite and non-zero.');
        }

        if (!is_finite($offset)) {
            throw new InvalidFitProfile('FIT field offset must be finite.');
        }

        return new self(
            scale: $scale,
            offset: $offset,
        );
    }

    public function isIdentity(): bool
    {
        return 1.0 === $this->scale
            && 0.0 === $this->offset;
    }

    public function apply(
        int|float $rawValue,
    ): int|float {
        $value = (
            $rawValue / $this->scale
        ) - $this->offset;

        return $this->normalize(
            $value,
            'FIT field transformation',
        );
    }

    private function normalize(
        float $value,
        string $description,
    ): int|float {
        if (!is_finite($value)) {
            throw new InvalidFitProfile(sprintf('%s produced a non-finite value.', $description));
        }

        $rounded = round($value);

        $tolerance = PHP_FLOAT_EPSILON
            * max(
                1.0,
                abs($value),
            )
            * self::INTEGER_TOLERANCE_MULTIPLIER;

        if (
            abs($value - $rounded) <= $tolerance
            && PHP_INT_MIN <= $rounded
            && PHP_INT_MAX >= $rounded
        ) {
            return (int) $rounded;
        }

        return $value;
    }

    public function toRaw(
        int|float $physicalValue,
    ): int|float {
        $value = (
            $physicalValue + $this->offset
        ) * $this->scale;

        return $this->normalize(
            $value,
            'Inverse FIT field transformation',
        );
    }
}
