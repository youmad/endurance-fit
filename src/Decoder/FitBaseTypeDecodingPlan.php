<?php

declare(strict_types=1);

namespace Endurance\Fit\Decoder;

use Endurance\Fit\Raw\FitArchitecture;
use Endurance\Fit\Raw\FitBaseType;
use Endurance\Fit\Raw\FitBaseTypeKind;

/**
 * @internal compiled immutable metadata for repeated decoding of one FIT
 * base type under one message architecture
 */
final readonly class FitBaseTypeDecodingPlan
{
    public function __construct(
        public FitBaseType $baseType,
        public FitArchitecture $architecture,
        public ?FitBaseTypeKind $kind,
        public int $fieldSize,
        public int $elementSize,
        public string $invalidBytes,
    ) {
    }
}
