<?php

declare(strict_types=1);

namespace Endurance\Fit\Typed;

use Endurance\Fit\Decoded\DecodedFieldValue;

final readonly class UnavailableTypedFieldValue implements TypedFieldValue
{
    public function __construct(
        public DecodedFieldValue $source,
        public string $reason,
    ) {
        if (
            '' === $reason
            || trim($reason) !== $reason
        ) {
            throw new \InvalidArgumentException('Unavailable FIT type resolution must have a reason.');
        }
    }
}
