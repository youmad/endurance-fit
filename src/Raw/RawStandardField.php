<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;

final readonly class RawStandardField
{
    public function __construct(
        public StandardFieldDefinition $definition,
        public RawFieldValue $value,
    ) {
        if ($definition->size !== $value->size()) {
            throw new InvalidRawFitMessage(sprintf('Standard FIT field %d expects %d bytes, %d bytes given.', $definition->fieldNumber, $definition->size, $value->size()));
        }
    }
}
