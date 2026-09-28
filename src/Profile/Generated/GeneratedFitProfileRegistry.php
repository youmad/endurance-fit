<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generated;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\FitProfileRegistry;
use Youmad\Endurance\Fit\Profile\MessageProfile;
use Youmad\Endurance\Fit\Profile\SubfieldCondition;
use Youmad\Endurance\Fit\Profile\SubfieldProfile;

final readonly class GeneratedFitProfileRegistry implements FitProfileRegistry
{
    /** @var array<int, MessageProfile> */
    private array $messages;

    public string $sourceSha256;

    public function __construct(string $dataFile)
    {
        if (!is_file($dataFile)) {
            throw new InvalidFitProfile(sprintf('Generated FIT message data file %s does not exist.', $dataFile));
        }

        $data = require $dataFile;

        if (!is_array($data)) {
            throw new InvalidFitProfile('Generated FIT message data must return an array.');
        }

        $sourceSha256 = $data['source_sha256'] ?? null;
        $messageData = $data['messages'] ?? null;

        if (
            !is_string($sourceSha256)
            || 1 !== preg_match('/^[a-f0-9]{64}$/', $sourceSha256)
            || !is_array($messageData)
        ) {
            throw new InvalidFitProfile('Generated FIT message data has invalid metadata.');
        }

        $messages = [];

        foreach ($messageData as $globalMessageNumber => $definition) {
            if (
                !is_int($globalMessageNumber)
                || !is_array($definition)
            ) {
                throw new InvalidFitProfile('Generated FIT message definition has invalid shape.');
            }

            $name = $definition['name'] ?? null;
            $fieldsData = $definition['fields'] ?? null;

            if (
                !is_string($name)
                || !is_array($fieldsData)
            ) {
                throw new InvalidFitProfile(sprintf('Generated FIT message %d has invalid definition.', $globalMessageNumber));
            }

            $fields = [];

            foreach ($fieldsData as $fieldData) {
                $fields[] = $this->hydrateField(
                    globalMessageNumber: $globalMessageNumber,
                    fieldData: $fieldData,
                );
            }

            $messages[$globalMessageNumber] = MessageProfile::create(
                globalMessageNumber: $globalMessageNumber,
                name: $name,
                fields: $fields,
            );
        }

        $this->sourceSha256 = $sourceSha256;
        $this->messages = $messages;
    }

    public function message(
        int $globalMessageNumber,
    ): ?MessageProfile {
        return $this->messages[$globalMessageNumber]
            ?? null;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function fieldCount(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            $count += count($message->fields());
        }

        return $count;
    }

    public function subfieldCount(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            foreach ($message->fields() as $field) {
                $count += count($field->subfields());
            }
        }

        return $count;
    }

    public function componentCount(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            foreach ($message->fields() as $field) {
                $count += count($field->components());

                foreach ($field->subfields() as $subfield) {
                    $count += count($subfield->components());
                }
            }
        }

        return $count;
    }

    private function hydrateField(
        int $globalMessageNumber,
        mixed $fieldData,
    ): FieldProfile {
        if (!is_array($fieldData)) {
            throw new InvalidFitProfile(sprintf('Generated FIT message %d has invalid field definition.', $globalMessageNumber));
        }

        $fieldNumber = $fieldData['field_number'] ?? null;
        $fieldName = $fieldData['name'] ?? null;
        $typeName = $fieldData['type'] ?? null;
        $scale = $fieldData['scale'] ?? null;
        $offset = $fieldData['offset'] ?? null;
        $units = $fieldData['units'] ?? null;
        $accumulated = $fieldData['accumulated'] ?? null;
        $componentsData = $fieldData['components'] ?? null;
        $subfieldsData = $fieldData['subfields'] ?? null;

        if (
            !is_int($fieldNumber)
            || !is_string($fieldName)
            || !is_string($typeName)
            || !$this->isOptionalNumber($scale)
            || !$this->isOptionalNumber($offset)
            || (
                null !== $units
                && !is_string($units)
            )
            || !is_bool($accumulated)
            || !is_array($componentsData)
            || !is_array($subfieldsData)
        ) {
            throw new InvalidFitProfile(sprintf('Generated FIT message %d has invalid field metadata.', $globalMessageNumber));
        }

        $components = $this->hydrateComponents(
            globalMessageNumber: $globalMessageNumber,
            ownerDescription: sprintf(
                'field %d',
                $fieldNumber,
            ),
            componentsData: $componentsData,
        );
        $subfields = [];

        foreach ($subfieldsData as $subfieldData) {
            $subfields[] = $this->hydrateSubfield(
                globalMessageNumber: $globalMessageNumber,
                fieldNumber: $fieldNumber,
                subfieldData: $subfieldData,
            );
        }

        return FieldProfile::create(
            fieldNumber: $fieldNumber,
            name: $fieldName,
            typeName: $typeName,
            transform: $this->transform(
                scale: $scale,
                offset: $offset,
            ),
            units: $units,
            subfields: $subfields,
            components: $components,
            accumulated: $accumulated,
        );
    }

    private function hydrateSubfield(
        int $globalMessageNumber,
        int $fieldNumber,
        mixed $subfieldData,
    ): SubfieldProfile {
        if (!is_array($subfieldData)) {
            throw new InvalidFitProfile(sprintf('Generated FIT message %d field %d has invalid subfield definition.', $globalMessageNumber, $fieldNumber));
        }

        $name = $subfieldData['name'] ?? null;
        $typeName = $subfieldData['type'] ?? null;
        $scale = $subfieldData['scale'] ?? null;
        $offset = $subfieldData['offset'] ?? null;
        $units = $subfieldData['units'] ?? null;
        $conditionsData = $subfieldData['conditions'] ?? null;
        $componentsData = $subfieldData['components'] ?? null;

        if (
            !is_string($name)
            || !is_string($typeName)
            || !$this->isOptionalNumber($scale)
            || !$this->isOptionalNumber($offset)
            || (
                null !== $units
                && !is_string($units)
            )
            || !is_array($conditionsData)
            || !is_array($componentsData)
        ) {
            throw new InvalidFitProfile(sprintf('Generated FIT message %d field %d has invalid subfield metadata.', $globalMessageNumber, $fieldNumber));
        }

        $conditions = [];

        foreach ($conditionsData as $conditionData) {
            if (!is_array($conditionData)) {
                throw new InvalidFitProfile(sprintf('Generated FIT message %d field %d subfield %s has invalid condition.', $globalMessageNumber, $fieldNumber, $name));
            }

            $referenceFieldNumber =
                $conditionData['reference_field_number']
                ?? null;
            $acceptedRawValues =
                $conditionData['accepted_raw_values']
                ?? null;

            if (
                !is_int($referenceFieldNumber)
                || !is_array($acceptedRawValues)
                || !array_is_list($acceptedRawValues)
            ) {
                throw new InvalidFitProfile(sprintf('Generated FIT message %d field %d subfield %s has invalid condition metadata.', $globalMessageNumber, $fieldNumber, $name));
            }

            foreach ($acceptedRawValues as $acceptedRawValue) {
                if (!is_int($acceptedRawValue)) {
                    throw new InvalidFitProfile(sprintf('Generated FIT message %d field %d subfield %s has a non-integer condition value.', $globalMessageNumber, $fieldNumber, $name));
                }
            }

            /* @var list<int> $acceptedRawValues */
            $conditions[] = SubfieldCondition::create(
                referenceFieldNumber: $referenceFieldNumber,
                acceptedRawValues: $acceptedRawValues,
            );
        }

        return SubfieldProfile::create(
            name: $name,
            typeName: $typeName,
            transform: $this->transform(
                scale: $scale,
                offset: $offset,
            ),
            units: $units,
            conditions: $conditions,
            components: $this->hydrateComponents(
                globalMessageNumber: $globalMessageNumber,
                ownerDescription: sprintf(
                    'field %d subfield %s',
                    $fieldNumber,
                    $name,
                ),
                componentsData: $componentsData,
            ),
        );
    }

    /**
     * @param array<mixed> $componentsData
     *
     * @return list<ComponentProfile>
     */
    private function hydrateComponents(
        int $globalMessageNumber,
        string $ownerDescription,
        array $componentsData,
    ): array {
        $components = [];

        foreach ($componentsData as $componentData) {
            if (!is_array($componentData)) {
                throw new InvalidFitProfile(sprintf('Generated FIT message %d %s has invalid component definition.', $globalMessageNumber, $ownerDescription));
            }

            $targetFieldNumber =
                $componentData['target_field_number']
                ?? null;
            $bits = $componentData['bits'] ?? null;
            $scale = $componentData['scale'] ?? null;
            $offset = $componentData['offset'] ?? null;
            $units = $componentData['units'] ?? null;
            $accumulated = $componentData['accumulated'] ?? null;
            $signed = $componentData['signed'] ?? null;

            if (
                !is_int($targetFieldNumber)
                || !is_int($bits)
                || !$this->isOptionalNumber($scale)
                || !$this->isOptionalNumber($offset)
                || (
                    null !== $units
                    && !is_string($units)
                )
                || !is_bool($accumulated)
                || !is_bool($signed)
            ) {
                throw new InvalidFitProfile(sprintf('Generated FIT message %d %s has invalid component metadata.', $globalMessageNumber, $ownerDescription));
            }

            $components[] = ComponentProfile::create(
                targetFieldNumber: $targetFieldNumber,
                bits: $bits,
                transform: $this->transform(
                    scale: $scale,
                    offset: $offset,
                ),
                units: $units,
                accumulated: $accumulated,
                signed: $signed,
            );
        }

        return $components;
    }

    private function transform(
        int|float|null $scale,
        int|float|null $offset,
    ): ?FieldTransform {
        if (
            null === $scale
            && null === $offset
        ) {
            return null;
        }

        return FieldTransform::scaleAndOffset(
            scale: $scale ?? 1,
            offset: $offset ?? 0,
        );
    }

    private function isOptionalNumber(
        mixed $value,
    ): bool {
        return null === $value
            || is_int($value)
            || is_float($value);
    }
}
