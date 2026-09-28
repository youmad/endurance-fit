<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

use Youmad\Endurance\Fit\Decoded\DecodedFieldValue;
use Youmad\Endurance\Fit\Developer\DeveloperDataProfile;
use Youmad\Endurance\Fit\Developer\DeveloperFieldProfile;
use Youmad\Endurance\Fit\Profile\FitTypeProfile;
use Youmad\Endurance\Fit\Profiled\ProfileNormalizationState;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Typed\TypedFieldValue;
use Youmad\Endurance\Fit\Typed\TypeResolutionState;

final readonly class UnifiedDeveloperField
{
    /**
     * @var list<UnifiedDeveloperComponent>
     */
    private array $components;

    /**
     * @param list<UnifiedDeveloperComponent> $components
     */
    private function __construct(
        public RawDeveloperField $source,
        public ?DeveloperDataProfile $developerData,
        public ?DeveloperFieldProfile $profile,
        public ?DecodedFieldValue $decodedValue,
        public ?DecodedFieldValue $physicalValue,
        public ?FitTypeProfile $typeProfile,
        public ?TypedFieldValue $value,
        array $components,
        public ?ProfileNormalizationState $normalizationState,
        public ?TypeResolutionState $typeResolutionState,
        public ?string $unavailableReason,
    ) {
        $this->components = $components;
    }

    public static function unresolved(
        RawDeveloperField $source,
        ?DeveloperDataProfile $developerData,
        string $reason,
    ): self {
        if (
            '' === $reason
            || trim($reason) !== $reason
        ) {
            throw new \InvalidArgumentException('Unavailable FIT developer field must have a reason.');
        }

        return new self(
            source: $source,
            developerData: $developerData,
            profile: null,
            decodedValue: null,
            physicalValue: null,
            typeProfile: null,
            value: null,
            components: [],
            normalizationState: null,
            typeResolutionState: null,
            unavailableReason: $reason,
        );
    }

    /**
     * @param array<array-key, mixed> $components
     */
    public static function resolved(
        RawDeveloperField $source,
        ?DeveloperDataProfile $developerData,
        DeveloperFieldProfile $profile,
        DecodedFieldValue $decodedValue,
        DecodedFieldValue $physicalValue,
        ?FitTypeProfile $typeProfile,
        TypedFieldValue $value,
        ProfileNormalizationState $normalizationState,
        TypeResolutionState $typeResolutionState,
        array $components = [],
    ): self {
        if (
            $source->definition->developerDataIndex
                !== $profile->developerDataIndex
            || $source->definition->fieldNumber
                !== $profile->fieldDefinitionNumber
        ) {
            throw new \InvalidArgumentException('FIT developer field profile does not match its raw definition.');
        }

        if (
            !$profile->baseType->equals(
                $decodedValue->baseType(),
            )
            || !$profile->baseType->equals(
                $physicalValue->baseType(),
            )
        ) {
            throw new \InvalidArgumentException('FIT developer field values must preserve the described base type.');
        }

        if (
            null !== $developerData
            && $developerData->developerDataIndex
                !== $profile->developerDataIndex
        ) {
            throw new \InvalidArgumentException('FIT developer identity does not match its field profile.');
        }

        $components = array_values($components);

        if (count($components) > count($profile->componentProfiles())) {
            throw new \InvalidArgumentException('Unified FIT developer field cannot contain more components than described.');
        }

        foreach ($components as $index => $component) {
            if (
                !$component instanceof UnifiedDeveloperComponent
                || $component->source !== $source
                || $component->fieldProfile !== $profile
                || $component->componentIndex !== $index
            ) {
                throw new \InvalidArgumentException(sprintf('Unified FIT developer component at position %d does not match its field.', $index));
            }
        }

        /* @var list<UnifiedDeveloperComponent> $components Validated above. */
        return new self(
            source: $source,
            developerData: $developerData,
            profile: $profile,
            decodedValue: $decodedValue,
            physicalValue: $physicalValue,
            typeProfile: $typeProfile,
            value: $value,
            components: $components,
            normalizationState: $normalizationState,
            typeResolutionState: $typeResolutionState,
            unavailableReason: null,
        );
    }

    /**
     * @return list<UnifiedDeveloperComponent>
     */
    public function components(): array
    {
        return $this->components;
    }

    public function hasComponents(): bool
    {
        return [] !== $this->components;
    }

    public function component(int $componentIndex): ?UnifiedDeveloperComponent
    {
        return $this->components[$componentIndex] ?? null;
    }

    public function isResolved(): bool
    {
        return null !== $this->profile;
    }

    public function name(): ?string
    {
        return $this->profile?->name;
    }

    public function units(): ?string
    {
        return $this->profile?->effectiveUnits();
    }

    public function developerDataIndex(): int
    {
        return $this->source
            ->definition
            ->developerDataIndex;
    }

    public function fieldDefinitionNumber(): int
    {
        return $this->source
            ->definition
            ->fieldNumber;
    }
}
