<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Decoded\DecodedFieldValue;

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
