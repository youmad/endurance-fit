<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Component;

use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\SubfieldProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledStandardField;

final readonly class ExtractedComponentField
{
    private function __construct(
        public ProfiledStandardField $container,
        public ComponentProfile $component,
        public FieldProfile $targetProfile,
        public int $componentIndex,
        public int $bitOffset,
        public int $packedValue,
        public ?SubfieldProfile $targetSubfield,
        public ?ResolvedComponentField $parent,
    ) {
    }

    public static function create(
        ProfiledStandardField $container,
        ComponentProfile $component,
        FieldProfile $targetProfile,
        int $componentIndex,
        int $bitOffset,
        int $packedValue,
        ?SubfieldProfile $targetSubfield = null,
        ?ResolvedComponentField $parent = null,
    ): self {
        if (0 > $componentIndex) {
            throw new \InvalidArgumentException('FIT component index cannot be negative.');
        }

        if (0 > $bitOffset) {
            throw new \InvalidArgumentException('FIT component bit offset cannot be negative.');
        }

        $minimumPackedValue = $component->signed
            ? -(1 << ($component->bits - 1))
            : 0;
        $maximumPackedValue = $component->signed
            ? (1 << ($component->bits - 1)) - 1
            : (
                63 === $component->bits
                    ? PHP_INT_MAX
                    : (1 << $component->bits) - 1
            );

        if (
            $minimumPackedValue > $packedValue
            || $maximumPackedValue < $packedValue
        ) {
            throw new \InvalidArgumentException(sprintf('Extracted FIT component value %d does not fit into its %d-bit %s definition.', $packedValue, $component->bits, $component->signed ? 'signed' : 'unsigned'));
        }

        if (
            $component->targetFieldNumber
            !== $targetProfile->fieldNumber
        ) {
            throw new \InvalidArgumentException('FIT component target does not match its destination field profile.');
        }

        if (
            null !== $targetSubfield
            && !in_array(
                $targetSubfield,
                $targetProfile->subfields(),
                true,
            )
        ) {
            throw new \InvalidArgumentException('Selected FIT component target subfield does not belong to its destination field profile.');
        }

        if (null === $parent) {
            $effectiveComponents = null !== $container->subfield
                ? $container->subfield->components()
                : $container->profile?->components() ?? [];
        } else {
            if ($parent->source->container !== $container) {
                throw new \InvalidArgumentException('Nested FIT component must preserve its physical root container.');
            }

            $effectiveComponents = null !== $parent->source->targetSubfield
                ? $parent->source->targetSubfield->components()
                : $parent->source->targetProfile->components();
        }

        if (
            !in_array(
                $component,
                $effectiveComponents,
                true,
            )
        ) {
            throw new \InvalidArgumentException('FIT component does not belong to its effective container profile.');
        }

        return new self(
            container: $container,
            component: $component,
            targetProfile: $targetProfile,
            componentIndex: $componentIndex,
            bitOffset: $bitOffset,
            packedValue: $packedValue,
            targetSubfield: $targetSubfield,
            parent: $parent,
        );
    }

    public function targetFieldNumber(): int
    {
        return $this->targetProfile->fieldNumber;
    }

    public function name(): string
    {
        return $this->targetSubfield->name
            ?? $this->targetProfile->name;
    }

    public function typeName(): string
    {
        return $this->targetSubfield->typeName
            ?? $this->targetProfile->typeName;
    }

    public function units(): ?string
    {
        return $this->component->units
            ?? $this->targetSubfield->units
            ?? $this->targetProfile->units;
    }

    public function isAccumulated(): bool
    {
        return $this->component
            ->accumulated;
    }
}
