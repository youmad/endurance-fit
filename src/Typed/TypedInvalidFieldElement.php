<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Decoded\InvalidFieldElement;

final readonly class TypedInvalidFieldElement implements TypedFieldElement
{
    public function __construct(
        public InvalidFieldElement $source,
    ) {
    }
}
