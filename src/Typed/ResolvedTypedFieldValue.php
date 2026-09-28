<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Profile\FitTypeProfile;

final readonly class ResolvedTypedFieldValue
{
    public function __construct(
        public ?FitTypeProfile $typeProfile,
        public TypedFieldValue $value,
        public TypeResolutionState $resolutionState,
    ) {
    }
}
