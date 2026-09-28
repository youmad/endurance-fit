<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedComponentDefinition
{
    private function __construct(
        public int $targetFieldNumber,
        public int $bits,
        public int|float|null $scale,
        public int|float|null $offset,
        public ?string $units,
        public bool $accumulated,
        public bool $signed,
    ) {
    }

    public static function create(
        int $targetFieldNumber,
        int $bits,
        int|float|null $scale = null,
        int|float|null $offset = null,
        ?string $units = null,
        bool $accumulated = false,
        bool $signed = false,
    ): self {
        if (0 > $targetFieldNumber || 255 < $targetFieldNumber) {
            throw new ProfileGenerationException('FIT generated component target field number must be between 0 and 255.');
        }

        if (1 > $bits || 63 < $bits) {
            throw new ProfileGenerationException('FIT generated component size must be between 1 and 63 bits.');
        }

        if (
            null !== $scale
            && (
                !is_finite((float) $scale)
                || 0.0 === (float) $scale
            )
        ) {
            throw new ProfileGenerationException('FIT generated component scale must be finite and non-zero.');
        }

        if (
            null !== $offset
            && !is_finite((float) $offset)
        ) {
            throw new ProfileGenerationException('FIT generated component offset must be finite.');
        }

        if (
            null !== $units
            && (
                '' === $units
                || trim($units) !== $units
            )
        ) {
            throw new ProfileGenerationException('FIT generated component units must be a non-empty trimmed string.');
        }

        return new self(
            targetFieldNumber: $targetFieldNumber,
            bits: $bits,
            scale: $scale,
            offset: $offset,
            units: $units,
            accumulated: $accumulated,
            signed: $signed,
        );
    }
}
