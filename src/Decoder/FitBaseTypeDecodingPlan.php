<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\FitBaseTypeKind;

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
