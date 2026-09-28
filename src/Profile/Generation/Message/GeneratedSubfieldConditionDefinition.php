<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedSubfieldConditionDefinition
{
    /** @var non-empty-list<int> */
    private array $acceptedRawValues;

    /** @param non-empty-list<int> $acceptedRawValues */
    private function __construct(
        public int $referenceFieldNumber,
        array $acceptedRawValues,
    ) {
        $this->acceptedRawValues = $acceptedRawValues;
    }

    /** @param array<array-key, mixed> $acceptedRawValues */
    public static function create(
        int $referenceFieldNumber,
        array $acceptedRawValues,
    ): self {
        if (0 > $referenceFieldNumber || 255 < $referenceFieldNumber) {
            throw new ProfileGenerationException('FIT generated subfield reference field number must be between 0 and 255.');
        }

        if ([] === $acceptedRawValues) {
            throw new ProfileGenerationException('FIT generated subfield condition must accept at least one raw value.');
        }

        $values = [];
        $seen = [];

        foreach ($acceptedRawValues as $value) {
            if (!is_int($value)) {
                throw new ProfileGenerationException('FIT generated subfield condition values must be integers.');
            }

            if (isset($seen[$value])) {
                throw new ProfileGenerationException(sprintf('FIT generated subfield condition defines raw value %d more than once.', $value));
            }

            $seen[$value] = true;
            $values[] = $value;
        }

        /* @var non-empty-list<int> $values */
        return new self(
            referenceFieldNumber: $referenceFieldNumber,
            acceptedRawValues: $values,
        );
    }

    /** @return non-empty-list<int> */
    public function acceptedRawValues(): array
    {
        return $this->acceptedRawValues;
    }
}
