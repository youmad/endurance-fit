<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

use Youmad\Endurance\Fit\Typed\TypedComponentDataMessage;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;

final readonly class UnifiedDataMessage
{
    /**
     * @var list<UnifiedStandardField>
     */
    private array $standardFields;

    /**
     * @var list<UnifiedDeveloperField>
     */
    private array $developerFields;

    /**
     * @param list<UnifiedStandardField>  $standardFields
     * @param list<UnifiedDeveloperField> $developerFields
     */
    private function __construct(
        public TypedDataMessage $physicalSource,
        public TypedComponentDataMessage $componentSource,
        array $standardFields,
        array $developerFields,
    ) {
        $this->standardFields = $standardFields;
        $this->developerFields = $developerFields;
    }

    /**
     * @param array<array-key, UnifiedStandardField>  $standardFields
     * @param array<array-key, UnifiedDeveloperField> $developerFields
     */
    public static function create(
        TypedDataMessage $physicalSource,
        TypedComponentDataMessage $componentSource,
        array $standardFields,
        array $developerFields = [],
    ): self {
        if (
            $physicalSource->source
            !== $componentSource->profiledSource()
        ) {
            throw new \InvalidArgumentException('Unified FIT message sources must originate from the same profiled message.');
        }

        $standardFields = array_values(
            $standardFields,
        );

        $developerFields = array_values(
            $developerFields,
        );

        $fieldNumbers = [];
        $physicalValues = [];
        $componentValues = [];

        foreach ($standardFields as $field) {
            if (isset($fieldNumbers[$field->fieldNumber])) {
                throw new \InvalidArgumentException(sprintf('Unified FIT message contains field %d more than once.', $field->fieldNumber));
            }

            $fieldNumbers[$field->fieldNumber] = true;

            $physical = $field->physical();

            if (null !== $physical) {
                if (
                    !in_array(
                        $physical->source,
                        $physicalSource->standardFields(),
                        true,
                    )
                ) {
                    throw new \InvalidArgumentException('Unified physical FIT value does not belong to its typed source message.');
                }

                $physicalValues[
                    spl_object_id($physical->source)
                ] = true;
            }

            foreach ($field->components() as $component) {
                if (
                    !in_array(
                        $component->source,
                        $componentSource->components(),
                        true,
                    )
                ) {
                    throw new \InvalidArgumentException('Unified component FIT value does not belong to its typed component source message.');
                }

                $componentValues[
                    spl_object_id($component->source)
                ] = true;
            }
        }

        if (
            count($physicalValues)
            !== count($physicalSource->standardFields())
        ) {
            throw new \InvalidArgumentException('Unified FIT message must contain every physical standard field.');
        }

        if (
            count($componentValues)
            !== count($componentSource->components())
        ) {
            throw new \InvalidArgumentException('Unified FIT message must contain every component-derived field value.');
        }

        $rawDeveloperFields = $physicalSource
            ->developerFields();

        if (
            count($rawDeveloperFields)
            !== count($developerFields)
        ) {
            throw new \InvalidArgumentException('Unified FIT message must contain every raw developer field.');
        }

        foreach (
            $rawDeveloperFields as $index => $rawDeveloperField
        ) {
            if (
                $developerFields[$index]->source
                !== $rawDeveloperField
            ) {
                throw new \InvalidArgumentException(sprintf('Unified FIT developer field at position %d does not match its raw source.', $index));
            }
        }

        return new self(
            physicalSource: $physicalSource,
            componentSource: $componentSource,
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }

    /**
     * @param list<UnifiedStandardField>  $standardFields
     * @param list<UnifiedDeveloperField> $developerFields
     *
     * @internal
     */
    public static function fromPipeline(
        TypedDataMessage $physicalSource,
        TypedComponentDataMessage $componentSource,
        array $standardFields,
        array $developerFields = [],
    ): self {
        return new self(
            physicalSource: $physicalSource,
            componentSource: $componentSource,
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }

    /**
     * @return list<UnifiedStandardField>
     */
    public function standardFields(): array
    {
        return $this->standardFields;
    }

    public function standardField(
        int $fieldNumber,
    ): ?UnifiedStandardField {
        foreach ($this->standardFields as $field) {
            if ($fieldNumber === $field->fieldNumber) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<UnifiedDeveloperField>
     */
    public function developerFields(): array
    {
        return $this->developerFields;
    }

    public function developerField(
        int $developerDataIndex,
        int $fieldDefinitionNumber,
    ): ?UnifiedDeveloperField {
        foreach ($this->developerFields as $field) {
            if (
                $developerDataIndex
                    === $field->developerDataIndex()
                && $fieldDefinitionNumber
                    === $field->fieldDefinitionNumber()
            ) {
                return $field;
            }
        }

        return null;
    }

    public function sequence(): int
    {
        return $this->physicalSource->sequence();
    }

    public function byteOffset(): int
    {
        return $this->physicalSource->byteOffset();
    }

    public function globalMessageNumber(): int
    {
        return $this->physicalSource
            ->globalMessageNumber();
    }

    public function name(): ?string
    {
        return $this->physicalSource->name();
    }
}
