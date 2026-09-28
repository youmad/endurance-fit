<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Decoded\ValidFieldElement;

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
