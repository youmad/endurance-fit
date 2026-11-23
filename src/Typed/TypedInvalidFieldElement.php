<?php

declare(strict_types=1);

namespace Endurance\Fit\Typed;

use Endurance\Fit\Decoded\InvalidFieldElement;

final readonly class TypedInvalidFieldElement implements TypedFieldElement
{
    public function __construct(
        public InvalidFieldElement $source,
    ) {
    }
}
