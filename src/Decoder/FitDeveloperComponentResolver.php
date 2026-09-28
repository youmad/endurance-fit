<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Developer\DeveloperFieldProfile;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Profiled\ProfileNormalizationState;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Unified\UnifiedDeveloperComponent;

final class FitDeveloperComponentResolver
{
    public function __construct(
        private readonly FitDeveloperComponentAccumulator $accumulator =
            new FitDeveloperComponentAccumulator(),
        private readonly FitFieldValueTransformer $transforms =
            new FitFieldValueTransformer(),
        private readonly FitTypedFieldValueResolver $typedValues =
            new FitTypedFieldValueResolver(),
    ) {
    }

    /**
     * @return list<UnifiedDeveloperComponent>
     */
    public function resolve(
        TypedDataMessage $message,
        RawDeveloperField $source,
        DeveloperFieldProfile $profile,
    ): array {
        $componentProfiles = $profile->componentProfiles();

        if ([] === $componentProfiles) {
            return [];
        }

        $bits = FitBitReader::fromDeveloperField(
            field: $source,
            baseType: $profile->baseType,
            architecture: $message->architecture(),
        );

        $components = [];

        foreach (
            $componentProfiles as $componentIndex => $componentProfile
        ) {
            if ($componentProfile->bits > $bits->remaining()) {
                break;
            }

            $bitOffset = $bits->position();
            $packedValue = $bits->readUnsigned(
                $componentProfile->bits,
            );

            $componentRawValue = $componentProfile->accumulated
                ? $this->accumulator->accumulate(
                    globalMessageNumber: $message->globalMessageNumber(),
                    developerDataIndex: $profile->developerDataIndex,
                    fieldDefinitionNumber: $profile->fieldDefinitionNumber,
                    componentIndex: $componentIndex,
                    packedValue: $packedValue,
                    bits: $componentProfile->bits,
                )
                : $packedValue;

            $decoded = DecodedFieldElements::create(
                $profile->baseType,
                new ValidFieldElement($componentRawValue),
            );

            $physical = $decoded;
            $normalizationState =
                ProfileNormalizationState::NotRequired;

            if (!$profile->transform->isIdentity()) {
                $transformed = $this->transforms->apply(
                    value: $decoded,
                    transform: $profile->transform,
                );

                if (null === $transformed) {
                    throw new FitDecodeException(sprintf('FIT developer component %d (%s) for field %d:%d cannot be normalized.', $componentIndex, $componentProfile->name, $profile->developerDataIndex, $profile->fieldDefinitionNumber));
                }

                $physical = $transformed;
                $normalizationState = $this
                    ->transforms
                    ->containsValidElement($transformed)
                    ? ProfileNormalizationState::Applied
                    : ProfileNormalizationState::NotRequired;
            }

            $typed = $this->typedValues->resolve(
                value: $physical,
                typeProfile: null,
            );

            $components[] = UnifiedDeveloperComponent::resolved(
                source: $source,
                fieldProfile: $profile,
                profile: $componentProfile,
                componentIndex: $componentIndex,
                bitOffset: $bitOffset,
                packedValue: $packedValue,
                componentRawValue: $componentRawValue,
                physicalValue: $physical,
                value: $typed->value,
                normalizationState: $normalizationState,
                typeResolutionState: $typed->resolutionState,
                accumulationApplied: $componentProfile->accumulated,
            );
        }

        return $components;
    }

    public function reset(): void
    {
        $this->accumulator->reset();
    }
}
