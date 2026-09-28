<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profiled;

use Youmad\Endurance\Fit\Decoded\DecodedFieldValue;
use Youmad\Endurance\Fit\Decoded\DecodedStandardField;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\SubfieldProfile;

final readonly class ProfiledStandardField
{
    private function __construct(
        public DecodedStandardField $source,
        public ?FieldProfile $profile,
        public ?SubfieldProfile $subfield,
        public DecodedFieldValue $value,
        public ProfileNormalizationState $normalizationState,
    ) {
    }

    public static function create(
        DecodedStandardField $source,
        ?FieldProfile $profile,
        DecodedFieldValue $value,
        ProfileNormalizationState $normalizationState,
        ?SubfieldProfile $subfield = null,
    ): self {
        if (
            !$source
                ->value
                ->baseType()
                ->equals(
                    $value->baseType(),
                )
        ) {
            throw new \InvalidArgumentException('Profiled FIT value must preserve its decoded base type.');
        }

        if (
            null !== $profile
            && $profile->fieldNumber
            !== $source->definition->fieldNumber
        ) {
            throw new \InvalidArgumentException('FIT field profile does not match its decoded field number.');
        }

        if (null !== $subfield) {
            if (
                null === $profile
                || !in_array(
                    $subfield,
                    $profile->subfields(),
                    true,
                )
            ) {
                throw new \InvalidArgumentException('Selected FIT subfield does not belong to its main field profile.');
            }
        }

        return new self(
            source: $source,
            profile: $profile,
            subfield: $subfield,
            value: $value,
            normalizationState: $normalizationState,
        );
    }

    /**
     * Internal hot-path constructor. The decoding pipeline already owns and
     * preserves the invariants enforced by create().
     *
     * @internal
     */
    public static function fromPipeline(
        DecodedStandardField $source,
        ?FieldProfile $profile,
        DecodedFieldValue $value,
        ProfileNormalizationState $normalizationState,
        ?SubfieldProfile $subfield = null,
    ): self {
        return new self(
            source: $source,
            profile: $profile,
            subfield: $subfield,
            value: $value,
            normalizationState: $normalizationState,
        );
    }

    public function fieldNumber(): int
    {
        return $this->source
            ->definition
            ->fieldNumber;
    }

    public function name(): ?string
    {
        return $this->subfield->name
            ?? $this->profile?->name;
    }

    public function mainName(): ?string
    {
        return $this->profile?->name;
    }

    public function typeName(): ?string
    {
        return $this->subfield->typeName
            ?? $this->profile?->typeName;
    }

    public function units(): ?string
    {
        return $this->subfield->units
            ?? $this->profile?->units;
    }

    public function isKnown(): bool
    {
        return null !== $this->profile;
    }

    public function hasSelectedSubfield(): bool
    {
        return null !== $this->subfield;
    }
}
