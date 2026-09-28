<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Component\ResolvedComponentField;
use Youmad\Endurance\Fit\Profile\FitTypeProfile;
use Youmad\Endurance\Fit\Profile\TypeValueProfile;

final readonly class TypedComponentField
{
    private function __construct(
        public ResolvedComponentField $source,
        public ?FitTypeProfile $typeProfile,
        public ?TypeValueProfile $valueProfile,
        public TypeResolutionState $resolutionState,
    ) {
    }

    public static function create(
        ResolvedComponentField $source,
        ?FitTypeProfile $typeProfile,
        ?TypeValueProfile $valueProfile,
        TypeResolutionState $resolutionState,
    ): self {
        if (
            null !== $typeProfile
            && $source->typeName() !== $typeProfile->name
        ) {
            throw new \InvalidArgumentException('Resolved FIT component type does not match its target field profile.');
        }

        match ($resolutionState) {
            TypeResolutionState::Applied => self::assertApplied(
                source: $source,
                typeProfile: $typeProfile,
                valueProfile: $valueProfile,
            ),

            TypeResolutionState::NotApplicable => self::assertNotApplicable(
                typeProfile: $typeProfile,
                valueProfile: $valueProfile,
            ),

            TypeResolutionState::Unavailable => self::assertUnavailable(
                source: $source,
                typeProfile: $typeProfile,
                valueProfile: $valueProfile,
            ),
        };

        return new self(
            source: $source,
            typeProfile: $typeProfile,
            valueProfile: $valueProfile,
            resolutionState: $resolutionState,
        );
    }

    public function typeName(): string
    {
        return $this->source->typeName();
    }

    private static function assertApplied(
        ResolvedComponentField $source,
        ?FitTypeProfile $typeProfile,
        ?TypeValueProfile $valueProfile,
    ): void {
        if (
            null === $typeProfile
            || !is_int($source->physicalValue)
        ) {
            throw new \InvalidArgumentException('Applied FIT component type resolution requires a type profile and an integer physical value.');
        }

        if (
            null !== $valueProfile
            && $valueProfile->value
            !== $source->physicalValue
        ) {
            throw new \InvalidArgumentException('FIT component symbolic value does not match its numeric physical value.');
        }
    }

    private static function assertNotApplicable(
        ?FitTypeProfile $typeProfile,
        ?TypeValueProfile $valueProfile,
    ): void {
        if (
            null !== $typeProfile
            || null !== $valueProfile
        ) {
            throw new \InvalidArgumentException('Non-applicable FIT component type resolution cannot contain symbolic profiles.');
        }
    }

    private static function assertUnavailable(
        ResolvedComponentField $source,
        ?FitTypeProfile $typeProfile,
        ?TypeValueProfile $valueProfile,
    ): void {
        if (
            null === $typeProfile
            || null !== $valueProfile
            || is_int($source->physicalValue)
        ) {
            throw new \InvalidArgumentException('Unavailable FIT component type resolution requires a known symbolic type with a non-integer physical value.');
        }
    }

    public function fieldNumber(): int
    {
        return $this->source
            ->targetFieldNumber();
    }

    public function name(): string
    {
        return $this->source->name();
    }

    public function units(): ?string
    {
        return $this->source->units();
    }

    public function value(): int|float
    {
        return $this->source
            ->physicalValue;
    }

    public function symbolicName(): ?string
    {
        return $this->valueProfile?->name;
    }

    public function isKnownSymbolicValue(): bool
    {
        return null !== $this->valueProfile;
    }
}
