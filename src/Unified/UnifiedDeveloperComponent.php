<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

use Youmad\Endurance\Fit\Decoded\DecodedFieldValue;
use Youmad\Endurance\Fit\Developer\DeveloperComponentProfile;
use Youmad\Endurance\Fit\Developer\DeveloperFieldProfile;
use Youmad\Endurance\Fit\Profiled\ProfileNormalizationState;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Typed\TypedFieldValue;
use Youmad\Endurance\Fit\Typed\TypeResolutionState;

final readonly class UnifiedDeveloperComponent
{
    private function __construct(
        public RawDeveloperField $source,
        public DeveloperFieldProfile $fieldProfile,
        public DeveloperComponentProfile $profile,
        public int $componentIndex,
        public int $bitOffset,
        public int $packedValue,
        public int $componentRawValue,
        public DecodedFieldValue $physicalValue,
        public TypedFieldValue $value,
        public ProfileNormalizationState $normalizationState,
        public TypeResolutionState $typeResolutionState,
        public bool $accumulationApplied,
    ) {
    }

    public static function resolved(
        RawDeveloperField $source,
        DeveloperFieldProfile $fieldProfile,
        DeveloperComponentProfile $profile,
        int $componentIndex,
        int $bitOffset,
        int $packedValue,
        int $componentRawValue,
        DecodedFieldValue $physicalValue,
        TypedFieldValue $value,
        ProfileNormalizationState $normalizationState,
        TypeResolutionState $typeResolutionState,
        bool $accumulationApplied,
    ): self {
        if (
            $source->definition->developerDataIndex
                !== $fieldProfile->developerDataIndex
            || $source->definition->fieldNumber
                !== $fieldProfile->fieldDefinitionNumber
        ) {
            throw new \InvalidArgumentException('FIT developer component source does not match its field profile.');
        }

        $profiles = $fieldProfile->componentProfiles();

        if (
            0 > $componentIndex
            || !isset($profiles[$componentIndex])
            || $profiles[$componentIndex] !== $profile
        ) {
            throw new \InvalidArgumentException('FIT developer component does not belong to its field profile.');
        }

        if (0 > $bitOffset) {
            throw new \InvalidArgumentException('FIT developer component bit offset cannot be negative.');
        }

        if (
            0 > $packedValue
            || 0 > $componentRawValue
        ) {
            throw new \InvalidArgumentException('FIT developer component values cannot be negative.');
        }

        if (
            !$accumulationApplied
            && $packedValue !== $componentRawValue
        ) {
            throw new \InvalidArgumentException('Non-accumulated FIT developer component must preserve its packed value.');
        }

        if (
            $accumulationApplied
            !== $profile->accumulated
        ) {
            throw new \InvalidArgumentException('FIT developer component accumulation state does not match its profile.');
        }

        if (!$fieldProfile->baseType->equals($physicalValue->baseType())) {
            throw new \InvalidArgumentException('FIT developer component physical value must preserve the described base type.');
        }

        return new self(
            source: $source,
            fieldProfile: $fieldProfile,
            profile: $profile,
            componentIndex: $componentIndex,
            bitOffset: $bitOffset,
            packedValue: $packedValue,
            componentRawValue: $componentRawValue,
            physicalValue: $physicalValue,
            value: $value,
            normalizationState: $normalizationState,
            typeResolutionState: $typeResolutionState,
            accumulationApplied: $accumulationApplied,
        );
    }

    public function name(): string
    {
        return $this->profile->name;
    }

    public function units(): ?string
    {
        return $this->fieldProfile->effectiveUnits();
    }
}
