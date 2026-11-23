<?php

declare(strict_types=1);

namespace Endurance\Fit\Typed;

use Endurance\Fit\Profile\FitTypeProfile;

final readonly class ResolvedTypedFieldValue
{
    public function __construct(
        public ?FitTypeProfile $typeProfile,
        public TypedFieldValue $value,
        public TypeResolutionState $resolutionState,
    ) {
    }
}
