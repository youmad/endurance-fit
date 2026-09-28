<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Typed\TypedComponentDataMessage;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Unified\ComponentFieldValue;
use Youmad\Endurance\Fit\Unified\PhysicalFieldValue;
use Youmad\Endurance\Fit\Unified\UnifiedDataMessage;
use Youmad\Endurance\Fit\Unified\UnifiedDeveloperField;
use Youmad\Endurance\Fit\Unified\UnifiedStandardField;

final readonly class FitDataMessageAssembler
{
    /**
     * @param list<UnifiedDeveloperField> $developerFields
     */
    public function assemble(
        TypedDataMessage $physical,
        TypedComponentDataMessage $components,
        array $developerFields = [],
    ): UnifiedDataMessage {
        return $this->assembleInternal(
            physical: $physical,
            components: $components,
            developerFields: $developerFields,
            trustedPipeline: false,
        );
    }

    /**
     * @param list<UnifiedDeveloperField> $developerFields
     *
     * @internal optimized decoder pipeline entry point
     */
    public function assembleForPipeline(
        TypedDataMessage $physical,
        TypedComponentDataMessage $components,
        array $developerFields = [],
    ): UnifiedDataMessage {
        return $this->assembleInternal(
            physical: $physical,
            components: $components,
            developerFields: $developerFields,
            trustedPipeline: true,
        );
    }

    /**
     * @param list<UnifiedDeveloperField> $developerFields
     */
    private function assembleInternal(
        TypedDataMessage $physical,
        TypedComponentDataMessage $components,
        array $developerFields,
        bool $trustedPipeline,
    ): UnifiedDataMessage {
        if (
            $physical->source
            !== $components->profiledSource()
        ) {
            throw new \InvalidArgumentException('FIT physical and component messages must originate from the same profiled message.');
        }

        /** @var array<int, PhysicalFieldValue> $physicalByField */
        $physicalByField = [];

        /** @var array<int, list<ComponentFieldValue>> $componentsByField */
        $componentsByField = [];

        /** @var list<int> $fieldOrder */
        $fieldOrder = [];

        /** @var array<int, true> $knownFields */
        $knownFields = [];

        foreach ($physical->standardFields() as $field) {
            $fieldNumber = $field->fieldNumber();

            $physicalByField[$fieldNumber] =
                new PhysicalFieldValue($field);

            $fieldOrder[] = $fieldNumber;
            $knownFields[$fieldNumber] = true;
        }

        foreach ($components->components() as $component) {
            $fieldNumber = $component->fieldNumber();

            $componentsByField[$fieldNumber][] =
                new ComponentFieldValue($component);

            if (!isset($knownFields[$fieldNumber])) {
                $fieldOrder[] = $fieldNumber;
                $knownFields[$fieldNumber] = true;
            }
        }

        $fields = [];

        foreach ($fieldOrder as $fieldNumber) {
            $physicalValue = $physicalByField[$fieldNumber]
                ?? null;
            $componentValues = $componentsByField[$fieldNumber]
                ?? [];

            $fields[] = $trustedPipeline
                ? UnifiedStandardField::fromPipeline(
                    fieldNumber: $fieldNumber,
                    physical: $physicalValue,
                    components: $componentValues,
                )
                : UnifiedStandardField::create(
                    physical: $physicalValue,
                    components: $componentValues,
                );
        }

        if ($trustedPipeline) {
            return UnifiedDataMessage::fromPipeline(
                physicalSource: $physical,
                componentSource: $components,
                standardFields: $fields,
                developerFields: $developerFields,
            );
        }

        return UnifiedDataMessage::create(
            physicalSource: $physical,
            componentSource: $components,
            standardFields: $fields,
            developerFields: $developerFields,
        );
    }
}
