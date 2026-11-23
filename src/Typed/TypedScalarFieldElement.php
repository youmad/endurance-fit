<?php

declare(strict_types=1);

namespace Endurance\Fit\Typed;

use Endurance\Fit\Decoded\ValidFieldElement;

final readonly class TypedScalarFieldElement implements TypedFieldElement
{
    public function __construct(
        public ValidFieldElement $source,
    ) {
    }

    public function value(): int|float|string
    {
        return $this->source->value;
    }
}
