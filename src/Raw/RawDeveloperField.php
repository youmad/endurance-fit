<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;

final readonly class RawDeveloperField
{
    public function __construct(
        public DeveloperFieldDefinition $definition,
        public RawFieldValue $value,
    ) {
        if ($definition->size !== $value->size()) {
            throw new InvalidRawFitMessage(sprintf('Developer FIT field %d for developer index %d expects %d bytes, %d bytes given.', $definition->fieldNumber, $definition->developerDataIndex, $definition->size, $value->size()));
        }
    }
}
