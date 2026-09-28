<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Profile\FitTypeProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledStandardField;

final readonly class TypedStandardField
{
    private function __construct(
        public ProfiledStandardField $source,
        public ?FitTypeProfile $typeProfile,
        public TypedFieldValue $value,
        public TypeResolutionState $resolutionState,
    ) {
    }

    public static function create(
        ProfiledStandardField $source,
        ?FitTypeProfile $typeProfile,
        TypedFieldValue $value,
        TypeResolutionState $resolutionState,
    ): self {
        if (
            null !== $typeProfile
            && $source->typeName() !== $typeProfile->name
        ) {
            throw new \InvalidArgumentException('Resolved FIT type does not match its field profile.');
        }

        match ($resolutionState) {
            TypeResolutionState::Applied => self::assertApplied(
                typeProfile: $typeProfile,
                value: $value,
            ),

            TypeResolutionState::NotApplicable => self::assertNotApplicable(
                typeProfile: $typeProfile,
                value: $value,
            ),

            TypeResolutionState::Unavailable => self::assertUnavailable($value),
        };

        return new self(
            source: $source,
            typeProfile: $typeProfile,
            value: $value,
            resolutionState: $resolutionState,
        );
    }

    /**
     * Internal hot-path constructor. FitTypeValueResolver has already
     * established the invariants enforced by create().
     *
     * @internal
     */
    public static function fromPipeline(
        ProfiledStandardField $source,
        ?FitTypeProfile $typeProfile,
        TypedFieldValue $value,
        TypeResolutionState $resolutionState,
    ): self {
        return new self(
            source: $source,
            typeProfile: $typeProfile,
            value: $value,
            resolutionState: $resolutionState,
        );
    }

    public function typeName(): ?string
    {
        return $this->source->typeName();
    }

    private static function assertApplied(
        ?FitTypeProfile $typeProfile,
        TypedFieldValue $value,
    ): void {
        if (
            null === $typeProfile
            || !$value instanceof TypedFieldElements
        ) {
            throw new \InvalidArgumentException('Applied FIT type resolution requires a type profile and typed elements.');
        }
    }

    private static function assertNotApplicable(
        ?FitTypeProfile $typeProfile,
        TypedFieldValue $value,
    ): void {
        if (
            null !== $typeProfile
            || !$value instanceof TypedFieldElements
        ) {
            throw new \InvalidArgumentException('Non-applicable FIT type resolution must preserve scalar elements without a type profile.');
        }
    }

    private static function assertUnavailable(
        TypedFieldValue $value,
    ): void {
        if (!$value instanceof UnavailableTypedFieldValue) {
            throw new \InvalidArgumentException('Unavailable FIT type resolution requires an unavailable value.');
        }
    }

    public function fieldNumber(): int
    {
        return $this->source->fieldNumber();
    }

    public function name(): ?string
    {
        return $this->source->name();
    }

    public function units(): ?string
    {
        return $this->source->units();
    }
}
