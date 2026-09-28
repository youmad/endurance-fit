<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class SubfieldCondition
{
    private const int MINIMUM_FIELD_NUMBER = 0;
    private const int MAXIMUM_FIELD_NUMBER = 255;

    /**
     * @var non-empty-list<int>
     */
    private array $acceptedRawValues;

    /**
     * @param non-empty-list<int> $acceptedRawValues
     */
    private function __construct(
        public int $referenceFieldNumber,
        array $acceptedRawValues,
    ) {
        $this->acceptedRawValues = $acceptedRawValues;
    }

    /**
     * @param array<array-key, mixed> $acceptedRawValues
     */
    public static function create(
        int $referenceFieldNumber,
        array $acceptedRawValues,
    ): self {
        if (
            self::MINIMUM_FIELD_NUMBER > $referenceFieldNumber
            || self::MAXIMUM_FIELD_NUMBER < $referenceFieldNumber
        ) {
            throw new InvalidFitProfile('FIT subfield reference field number must be between 0 and 255.');
        }

        if ([] === $acceptedRawValues) {
            throw new InvalidFitProfile('FIT subfield condition must accept at least one raw value.');
        }

        $uniqueValues = [];
        $seen = [];

        foreach ($acceptedRawValues as $value) {
            if (!is_int($value)) {
                throw new InvalidFitProfile('FIT subfield condition values must be integers.');
            }

            $key = (string) $value;

            if (isset($seen[$key])) {
                throw new InvalidFitProfile(sprintf('FIT subfield condition defines raw value %d more than once.', $value));
            }

            $seen[$key] = true;
            $uniqueValues[] = $value;
        }

        return new self(
            referenceFieldNumber: $referenceFieldNumber,
            acceptedRawValues: $uniqueValues,
        );
    }

    /**
     * @return non-empty-list<int>
     */
    public function acceptedRawValues(): array
    {
        return $this->acceptedRawValues;
    }

    public function accepts(int $rawValue): bool
    {
        return in_array(
            $rawValue,
            $this->acceptedRawValues,
            true,
        );
    }
}
