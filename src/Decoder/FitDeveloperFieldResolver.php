<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Developer\FitDeveloperProfileCollector;
use Youmad\Endurance\Fit\Developer\FitDeveloperProfileRegistry;
use Youmad\Endurance\Fit\Profile\FitProfileRegistry;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Profiled\ProfileNormalizationState;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Unified\UnifiedDeveloperField;

final class FitDeveloperFieldResolver
{
    private readonly FitDeveloperProfileRegistry $registry;

    private readonly FitDeveloperProfileCollector $collector;

    public function __construct(
        FitProfileRegistry $profiles,
        private readonly FitBaseTypeDecoder $baseTypes = new FitBaseTypeDecoder(),
        private readonly FitFieldValueTransformer $transforms = new FitFieldValueTransformer(),
        private readonly FitTypedFieldValueResolver $typedValues = new FitTypedFieldValueResolver(),
        private readonly FitDeveloperComponentResolver $components = new FitDeveloperComponentResolver(),
        ?FitDeveloperProfileRegistry $registry = null,
    ) {
        $this->registry = $registry
            ?? new FitDeveloperProfileRegistry();

        $this->collector = new FitDeveloperProfileCollector(
            registry: $this->registry,
            profiles: $profiles,
        );
    }

    /**
     * @return list<UnifiedDeveloperField>
     */
    public function resolve(
        TypedDataMessage $message,
    ): array {
        $this->collector->observe($message);

        return $this->resolveFields($message);
    }

    /**
     * Resolve developer payload for a Record whose standard fields have already
     * been Profile-normalized but intentionally not type-resolved. Record
     * messages do not define developer metadata, so the collector is not
     * involved; the shared registry populated by developer_data_id and
     * field_description messages remains authoritative.
     *
     * @return list<UnifiedDeveloperField>
     *
     * @internal optimized Record pipeline entry point
     */
    public function resolveRecord(
        ProfiledDataMessage $message,
    ): array {
        return $this->resolveFields(
            TypedDataMessage::fromPipeline(
                source: $message,
                standardFields: [],
            ),
        );
    }

    /**
     * @return list<UnifiedDeveloperField>
     */
    private function resolveFields(
        TypedDataMessage $message,
    ): array {
        $fields = [];

        foreach ($message->developerFields() as $source) {
            $developerDataIndex = $source
                ->definition
                ->developerDataIndex;

            $developerData = $this->registry
                ->developerData($developerDataIndex);

            $profile = $this->registry->field(
                developerDataIndex: $developerDataIndex,
                fieldDefinitionNumber: $source
                    ->definition
                    ->fieldNumber,
            );

            if (null === $profile) {
                $fields[] = UnifiedDeveloperField::unresolved(
                    source: $source,
                    developerData: $developerData,
                    reason: sprintf(
                        'No field_description was registered for developer data index %d and field %d.',
                        $developerDataIndex,
                        $source->definition->fieldNumber,
                    ),
                );

                continue;
            }

            $decodedValue = $this->baseTypes->decode(
                baseType: $profile->baseType,
                architecture: $message->architecture(),
                rawValue: $source->value,
            );

            $physicalValue = $decodedValue;
            $normalizationState =
                ProfileNormalizationState::NotRequired;

            if (!$profile->transform->isIdentity()) {
                $transformed = $this->transforms->apply(
                    value: $decodedValue,
                    transform: $profile->transform,
                );

                if (null === $transformed) {
                    $normalizationState =
                        ProfileNormalizationState::Unavailable;
                } else {
                    $physicalValue = $transformed;
                    $normalizationState = $this
                        ->transforms
                        ->containsValidElement($transformed)
                        ? ProfileNormalizationState::Applied
                        : ProfileNormalizationState::NotRequired;
                }
            }

            $typed = $this->typedValues->resolve(
                value: $physicalValue,
                typeProfile: null,
            );

            $developerComponents = $this->components->resolve(
                message: $message,
                source: $source,
                profile: $profile,
            );

            $fields[] = UnifiedDeveloperField::resolved(
                source: $source,
                developerData: $developerData,
                profile: $profile,
                decodedValue: $decodedValue,
                physicalValue: $physicalValue,
                typeProfile: $typed->typeProfile,
                value: $typed->value,
                normalizationState: $normalizationState,
                typeResolutionState: $typed->resolutionState,
                components: $developerComponents,
            );
        }

        return $fields;
    }

    public function reset(): void
    {
        $this->registry->reset();
        $this->components->reset();
    }
}
