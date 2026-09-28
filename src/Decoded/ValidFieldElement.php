<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoded;

final readonly class ValidFieldElement implements DecodedFieldElement
{
    public function __construct(
        public int|float|string $value,
    ) {
    }
}
