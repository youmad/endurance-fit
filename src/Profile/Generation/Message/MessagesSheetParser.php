<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;
use Youmad\Endurance\Fit\Profile\Generation\ProfileIdentifierNormalizer;
use Youmad\Endurance\Fit\Profile\Generation\Type\GeneratedTypes;
use Youmad\Endurance\Fit\Profile\Generation\Xlsx\SpreadsheetRow;

final readonly class MessagesSheetParser
{
    private const array EXPECTED_HEADER = [
        'Message Name',
        'Field Def #',
        'Field Name',
        'Field Type',
        'Array',
        'Components',
        'Scale',
        'Offset',
        'Units',
        'Bits',
        'Accumulate',
        'Ref Field Name',
        'Ref Field Value',
        'Comment',
    ];

    private const array PRIMITIVE_FIELD_TYPES = [
        'enum' => true,
        'sint8' => true,
        'uint8' => true,
        'sint16' => true,
        'uint16' => true,
        'sint32' => true,
        'uint32' => true,
        'string' => true,
        'float32' => true,
        'float64' => true,
        'uint8z' => true,
        'uint16z' => true,
        'uint32z' => true,
        'byte' => true,
        'sint64' => true,
        'uint64' => true,
        'uint64z' => true,
        'bool' => true,
    ];

    public function __construct(
        private ProfileIdentifierNormalizer $identifiers =
            new ProfileIdentifierNormalizer(),
    ) {
    }

    /** @param iterable<int, mixed> $rows */
    public function parse(
        iterable $rows,
        GeneratedTypes $types,
    ): GeneratedMessages {
        $messageNumbers = $types->type('mesg_num');

        if (null === $messageNumbers) {
            throw new ProfileGenerationException('FIT Types sheet does not define mesg_num.');
        }

        $headerRead = false;
        $messages = [];
        $current = null;
        $currentFieldIndex = null;

        foreach ($rows as $row) {
            if (!$row instanceof SpreadsheetRow) {
                throw new ProfileGenerationException('FIT Messages sheet parser expects SpreadsheetRow objects.');
            }

            if (!$headerRead) {
                $this->assertHeader($row);
                $headerRead = true;

                continue;
            }

            $sourceMessageName = trim($row->value(0));
            $rawFieldNumber = trim($row->value(1));
            $sourceFieldName = trim($row->value(2));
            $sourceFieldType = trim($row->value(3));

            if ('' !== $sourceMessageName) {
                if (null !== $current) {
                    $messages[] = $this->finishMessage(
                        message: $current,
                        types: $types,
                    );
                }

                $messageName = $this->identifiers->normalize(
                    $sourceMessageName,
                );
                $messageValue = $messageNumbers->valueByName(
                    $messageName,
                );

                if (null === $messageValue) {
                    throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d defines message %s missing from mesg_num.', $row->number, $messageName));
                }

                $current = [
                    'row' => $row->number,
                    'number' => $messageValue->value,
                    'name' => $messageName,
                    'fields' => [],
                    'accumulated_targets' => [],
                ];
                $currentFieldIndex = null;

                continue;
            }

            if ('' !== $rawFieldNumber) {
                if (null === $current) {
                    throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d defines a field before the first message.', $row->number));
                }

                $field = $this->parseMainField(
                    row: $row,
                    rawFieldNumber: $rawFieldNumber,
                    sourceFieldName: $sourceFieldName,
                    sourceFieldType: $sourceFieldType,
                    types: $types,
                );

                foreach ($field['components'] as $component) {
                    if ($component['accumulated']) {
                        $current['accumulated_targets'][
                            $component['target_name']
                        ] = $row->number;
                    }
                }

                $current['fields'][] = $field;
                $currentFieldIndex = array_key_last(
                    $current['fields'],
                );

                continue;
            }

            if (
                '' === $sourceFieldName
                && '' === $sourceFieldType
            ) {
                $currentFieldIndex = null;

                continue;
            }

            if (
                '' === $sourceFieldName
                || '' === $sourceFieldType
            ) {
                // Visual section rows use the Field Type column only.
                $currentFieldIndex = null;

                continue;
            }

            if (
                null === $current
                || null === $currentFieldIndex
            ) {
                throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d defines a subfield without a containing main field.', $row->number));
            }

            $subfield = $this->parseSubfield(
                row: $row,
                sourceFieldName: $sourceFieldName,
                sourceFieldType: $sourceFieldType,
                types: $types,
            );

            foreach ($subfield['components'] as $component) {
                if ($component['accumulated']) {
                    $current['accumulated_targets'][
                        $component['target_name']
                    ] = $row->number;
                }
            }

            $current['fields'][$currentFieldIndex]['subfields'][] =
                $subfield;
        }

        if (!$headerRead) {
            throw new ProfileGenerationException('FIT Messages sheet is empty.');
        }

        if (null !== $current) {
            $messages[] = $this->finishMessage(
                message: $current,
                types: $types,
            );
        }

        $pad = $messageNumbers->valueByName('pad');

        if (null !== $pad) {
            $messages[] = GeneratedMessageDefinition::create(
                globalMessageNumber: $pad->value,
                name: $pad->name,
            );
        }

        return GeneratedMessages::create($messages);
    }

    /**
     * @return array{
     *     row: int,
     *     field_number: int,
     *     name: string,
     *     type_name: string,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     components: list<array{
     *         target_name: string,
     *         bits: int,
     *         scale: int|float|null,
     *         offset: int|float|null,
     *         units: string|null,
     *         accumulated: bool
     *     }>,
     *     subfields: list<array{
     *         row: int,
     *         name: string,
     *         type_name: string,
     *         scale: int|float|null,
     *         offset: int|float|null,
     *         units: string|null,
     *         conditions: list<array{
     *             reference_field_name: string,
     *             reference_value: string
     *         }>,
     *         components: list<array{
     *             target_name: string,
     *             bits: int,
     *             scale: int|float|null,
     *             offset: int|float|null,
     *             units: string|null,
     *             accumulated: bool
     *         }>
     *     }>
     * }
     */
    private function parseMainField(
        SpreadsheetRow $row,
        string $rawFieldNumber,
        string $sourceFieldName,
        string $sourceFieldType,
        GeneratedTypes $types,
    ): array {
        if (
            '' === $sourceFieldName
            || '' === $sourceFieldType
        ) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d must define both Field Name and Field Type.', $row->number));
        }

        $name = $this->identifiers->normalize(
            $sourceFieldName,
        );
        $typeName = $this->identifiers->normalize(
            $sourceFieldType,
        );

        $this->assertKnownType(
            rowNumber: $row->number,
            fieldName: $name,
            typeName: $typeName,
            types: $types,
        );

        $components = $this->parseComponents($row);
        [$scale, $offset, $units] = $this->sourceMetadata(
            row: $row,
            components: $components,
        );

        return [
            'row' => $row->number,
            'field_number' => $this->parseUnsignedInteger(
                rawValue: $rawFieldNumber,
                maximum: 255,
                description: 'field number',
                rowNumber: $row->number,
            ),
            'name' => $name,
            'type_name' => $typeName,
            'scale' => $scale,
            'offset' => $offset,
            'units' => $units,
            'components' => $components,
            'subfields' => [],
        ];
    }

    /**
     * @return array{
     *     row: int,
     *     name: string,
     *     type_name: string,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     conditions: list<array{
     *         reference_field_name: string,
     *         reference_value: string
     *     }>,
     *     components: list<array{
     *         target_name: string,
     *         bits: int,
     *         scale: int|float|null,
     *         offset: int|float|null,
     *         units: string|null,
     *         accumulated: bool
     *     }>
     * }
     */
    private function parseSubfield(
        SpreadsheetRow $row,
        string $sourceFieldName,
        string $sourceFieldType,
        GeneratedTypes $types,
    ): array {
        $name = $this->identifiers->normalize(
            $sourceFieldName,
        );
        $typeName = $this->identifiers->normalize(
            $sourceFieldType,
        );

        $this->assertKnownType(
            rowNumber: $row->number,
            fieldName: $name,
            typeName: $typeName,
            types: $types,
        );

        $rawReferenceFields = trim($row->value(11));
        $rawReferenceValues = trim($row->value(12));

        if (
            '' === $rawReferenceFields
            || '' === $rawReferenceValues
        ) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d subfield %s must define reference field names and values.', $row->number, $name));
        }

        $referenceFields = $this->split(
            rawValue: $rawReferenceFields,
            description: 'reference field names',
            rowNumber: $row->number,
        );
        $referenceValues = $this->split(
            rawValue: $rawReferenceValues,
            description: 'reference field values',
            rowNumber: $row->number,
        );

        if (count($referenceFields) !== count($referenceValues)) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d has different Ref Field Name and Ref Field Value counts.', $row->number));
        }

        $conditions = [];

        foreach ($referenceFields as $index => $referenceField) {
            $conditions[] = [
                'reference_field_name' => $this->identifiers->normalize(
                    $referenceField,
                ),
                'reference_value' => $referenceValues[$index],
            ];
        }

        $components = $this->parseComponents($row);
        [$scale, $offset, $units] = $this->sourceMetadata(
            row: $row,
            components: $components,
        );

        return [
            'row' => $row->number,
            'name' => $name,
            'type_name' => $typeName,
            'scale' => $scale,
            'offset' => $offset,
            'units' => $units,
            'conditions' => $conditions,
            'components' => $components,
        ];
    }

    /**
     * A single-component field uses the same scale, offset and units
     * for its own value and for the expanded destination value.
     * Multi-component containers do not have one meaningful transform.
     *
     * @param list<array{
     *     target_name: string,
     *     bits: int,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     accumulated: bool
     * }> $components
     *
     * @return array{int|float|null, int|float|null, string|null}
     */
    private function sourceMetadata(
        SpreadsheetRow $row,
        array $components,
    ): array {
        if ([] === $components) {
            return [
                $this->parseOptionalNumber(
                    rawValue: trim($row->value(6)),
                    description: 'scale',
                    rowNumber: $row->number,
                    nonZero: true,
                ),
                $this->parseOptionalNumber(
                    rawValue: trim($row->value(7)),
                    description: 'offset',
                    rowNumber: $row->number,
                ),
                $this->optionalText($row->value(8)),
            ];
        }

        if (1 === count($components)) {
            return [
                $components[0]['scale'],
                $components[0]['offset'],
                $components[0]['units'],
            ];
        }

        return [null, null, null];
    }

    /**
     * @return list<array{
     *     target_name: string,
     *     bits: int,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     accumulated: bool
     * }>
     */
    private function parseComponents(
        SpreadsheetRow $row,
    ): array {
        $rawComponents = trim($row->value(5));

        if ('' === $rawComponents) {
            if ('' !== trim($row->value(10))) {
                throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d defines Accumulate without Components.', $row->number));
            }

            return [];
        }

        $targetNames = array_map(
            fn (string $value): string => $this->identifiers->normalize($value),
            $this->split(
                rawValue: $rawComponents,
                description: 'Components',
                rowNumber: $row->number,
            ),
        );
        $componentCount = count($targetNames);

        $bits = $this->expandColumn(
            rawValue: trim($row->value(9)),
            componentCount: $componentCount,
            description: 'Bits',
            rowNumber: $row->number,
            required: true,
        );
        $scales = $this->expandColumn(
            rawValue: trim($row->value(6)),
            componentCount: $componentCount,
            description: 'Scale',
            rowNumber: $row->number,
        );
        $offsets = $this->expandColumn(
            rawValue: trim($row->value(7)),
            componentCount: $componentCount,
            description: 'Offset',
            rowNumber: $row->number,
        );
        $units = $this->expandColumn(
            rawValue: trim($row->value(8)),
            componentCount: $componentCount,
            description: 'Units',
            rowNumber: $row->number,
        );
        $accumulate = $this->expandColumn(
            rawValue: trim($row->value(10)),
            componentCount: $componentCount,
            description: 'Accumulate',
            rowNumber: $row->number,
        );

        $components = [];

        foreach ($targetNames as $index => $targetName) {
            $rawAccumulate = $accumulate[$index];

            if (
                null !== $rawAccumulate
                && !in_array(
                    $rawAccumulate,
                    ['0', '1'],
                    true,
                )
            ) {
                throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d contains unsupported Accumulate flag %s.', $row->number, $rawAccumulate));
            }

            $components[] = [
                'target_name' => $targetName,
                'bits' => $this->parseUnsignedInteger(
                    rawValue: $bits[$index] ?? '',
                    maximum: 63,
                    description: 'component bit width',
                    rowNumber: $row->number,
                    minimum: 1,
                ),
                'scale' => $this->parseOptionalNumber(
                    rawValue: $scales[$index] ?? '',
                    description: 'component scale',
                    rowNumber: $row->number,
                    nonZero: true,
                ),
                'offset' => $this->parseOptionalNumber(
                    rawValue: $offsets[$index] ?? '',
                    description: 'component offset',
                    rowNumber: $row->number,
                ),
                'units' => $this->optionalText(
                    $units[$index] ?? '',
                ),
                'accumulated' => '1' === $rawAccumulate,
            ];
        }

        return $components;
    }

    /**
     * @return list<string|null>
     */
    private function expandColumn(
        string $rawValue,
        int $componentCount,
        string $description,
        int $rowNumber,
        bool $required = false,
    ): array {
        if ('' === $rawValue) {
            if ($required) {
                throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d defines Components without %s.', $rowNumber, $description));
            }

            return array_fill(
                start_index: 0,
                count: $componentCount,
                value: null,
            );
        }

        $values = array_map(
            static fn (string $value): ?string => '' === trim($value)
                    ? null
                    : trim($value),
            explode(',', $rawValue),
        );

        if (
            1 === count($values)
            && 1 < $componentCount
        ) {
            return array_fill(
                start_index: 0,
                count: $componentCount,
                value: $values[0],
            );
        }

        if ($componentCount !== count($values)) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d has %d Components but %d %s values.', $rowNumber, $componentCount, count($values), $description));
        }

        return $values;
    }

    /**
     * @param array{
     *     row: int,
     *     number: int,
     *     name: string,
     *     fields: list<array{
     *         row: int,
     *         field_number: int,
     *         name: string,
     *         type_name: string,
     *         scale: int|float|null,
     *         offset: int|float|null,
     *         units: string|null,
     *         components: list<array{
     *             target_name: string,
     *             bits: int,
     *             scale: int|float|null,
     *             offset: int|float|null,
     *             units: string|null,
     *             accumulated: bool
     *         }>,
     *         subfields: list<array{
     *             row: int,
     *             name: string,
     *             type_name: string,
     *             scale: int|float|null,
     *             offset: int|float|null,
     *             units: string|null,
     *             conditions: list<array{
     *                 reference_field_name: string,
     *                 reference_value: string
     *             }>,
     *             components: list<array{
     *                 target_name: string,
     *                 bits: int,
     *                 scale: int|float|null,
     *                 offset: int|float|null,
     *                 units: string|null,
     *                 accumulated: bool
     *             }>
     *         }>
     *     }>,
     *     accumulated_targets: array<string, int>
     * } $message
     */
    private function finishMessage(
        array $message,
        GeneratedTypes $types,
    ): GeneratedMessageDefinition {
        $fieldsByName = [];

        foreach ($message['fields'] as $field) {
            if (isset($fieldsByName[$field['name']])) {
                throw new ProfileGenerationException(sprintf('FIT message %s defines field name %s more than once; rows %d and %d.', $message['name'], $field['name'], $fieldsByName[$field['name']]['row'], $field['row']));
            }

            $fieldsByName[$field['name']] = $field;
        }

        foreach (
            $message['accumulated_targets'] as $target => $sourceRow
        ) {
            if (!isset($fieldsByName[$target])) {
                throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d marks unknown field %s in message %s as accumulated.', $sourceRow, $target, $message['name']));
            }
        }

        $fields = [];

        foreach ($message['fields'] as $field) {
            $subfields = [];

            foreach ($field['subfields'] as $subfield) {
                $subfields[] = $this->resolveSubfield(
                    subfield: $subfield,
                    messageName: $message['name'],
                    fieldsByName: $fieldsByName,
                    types: $types,
                );
            }

            $fields[] = GeneratedMessageFieldDefinition::create(
                fieldNumber: $field['field_number'],
                name: $field['name'],
                typeName: $field['type_name'],
                scale: $field['scale'],
                offset: $field['offset'],
                units: $field['units'],
                accumulated: isset(
                    $message['accumulated_targets'][$field['name']],
                ),
                subfields: $subfields,
                components: $this->resolveComponents(
                    components: $field['components'],
                    messageName: $message['name'],
                    fieldsByName: $fieldsByName,
                    types: $types,
                ),
            );
        }

        return GeneratedMessageDefinition::create(
            globalMessageNumber: $message['number'],
            name: $message['name'],
            fields: $fields,
        );
    }

    /**
     * @param array{
     *     row: int,
     *     name: string,
     *     type_name: string,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     conditions: list<array{
     *         reference_field_name: string,
     *         reference_value: string
     *     }>,
     *     components: list<array{
     *         target_name: string,
     *         bits: int,
     *         scale: int|float|null,
     *         offset: int|float|null,
     *         units: string|null,
     *         accumulated: bool
     *     }>
     * } $subfield
     * @param array<string, array{
     *     row: int,
     *     field_number: int,
     *     name: string,
     *     type_name: string,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     components: list<array{
     *         target_name: string,
     *         bits: int,
     *         scale: int|float|null,
     *         offset: int|float|null,
     *         units: string|null,
     *         accumulated: bool
     *     }>,
     *     subfields: list<mixed>
     * }> $fieldsByName
     */
    private function resolveSubfield(
        array $subfield,
        string $messageName,
        array $fieldsByName,
        GeneratedTypes $types,
    ): GeneratedSubfieldDefinition {
        /** @var array<int, list<int>> $valuesByReferenceField */
        $valuesByReferenceField = [];
        /** @var list<int> $referenceFieldOrder */
        $referenceFieldOrder = [];

        foreach ($subfield['conditions'] as $condition) {
            $referenceFieldName =
                $condition['reference_field_name'];
            $referenceField = $fieldsByName[$referenceFieldName]
                ?? null;

            if (null === $referenceField) {
                throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d subfield %s in message %s references unknown field %s.', $subfield['row'], $subfield['name'], $messageName, $referenceFieldName));
            }

            $referenceFieldNumber =
                $referenceField['field_number'];

            if (!isset($valuesByReferenceField[$referenceFieldNumber])) {
                $valuesByReferenceField[$referenceFieldNumber] = [];
                $referenceFieldOrder[] = $referenceFieldNumber;
            }

            $valuesByReferenceField[$referenceFieldNumber][] =
                $this->resolveReferenceValue(
                    rawValue: $condition['reference_value'],
                    referenceFieldType: $referenceField['type_name'],
                    types: $types,
                    rowNumber: $subfield['row'],
                    subfieldName: $subfield['name'],
                );
        }

        $conditions = [];

        foreach ($referenceFieldOrder as $referenceFieldNumber) {
            $conditions[] =
                GeneratedSubfieldConditionDefinition::create(
                    referenceFieldNumber: $referenceFieldNumber,
                    acceptedRawValues: $valuesByReferenceField[
                            $referenceFieldNumber
                        ],
                );
        }

        return GeneratedSubfieldDefinition::create(
            name: $subfield['name'],
            typeName: $subfield['type_name'],
            scale: $subfield['scale'],
            offset: $subfield['offset'],
            units: $subfield['units'],
            conditions: $conditions,
            components: $this->resolveComponents(
                components: $subfield['components'],
                messageName: $messageName,
                fieldsByName: $fieldsByName,
                types: $types,
            ),
        );
    }

    private function resolveReferenceValue(
        string $rawValue,
        string $referenceFieldType,
        GeneratedTypes $types,
        int $rowNumber,
        string $subfieldName,
    ): int {
        $type = $types->type($referenceFieldType);

        if (null !== $type) {
            $valueName = $this->identifiers->normalize(
                $rawValue,
            );
            $value = $type->valueByName($valueName);

            if (null !== $value) {
                return $value->value;
            }
        }

        if (
            1 === preg_match('/^[0-9]+$/', $rawValue)
            || 1 === preg_match('/^0[xX][0-9a-fA-F]+$/', $rawValue)
        ) {
            return $this->parseReferenceInteger(
                rawValue: $rawValue,
                rowNumber: $rowNumber,
            );
        }

        throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d subfield %s references unknown %s value %s.', $rowNumber, $subfieldName, $referenceFieldType, $rawValue));
    }

    /**
     * @param list<array{
     *     target_name: string,
     *     bits: int,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     accumulated: bool
     * }> $components
     * @param array<string, array{
     *     row: int,
     *     field_number: int,
     *     name: string,
     *     type_name: string,
     *     scale: int|float|null,
     *     offset: int|float|null,
     *     units: string|null,
     *     components: list<mixed>,
     *     subfields: list<mixed>
     * }> $fieldsByName
     *
     * @return list<GeneratedComponentDefinition>
     */
    private function resolveComponents(
        array $components,
        string $messageName,
        array $fieldsByName,
        GeneratedTypes $types,
    ): array {
        $resolved = [];

        foreach ($components as $component) {
            $target = $fieldsByName[$component['target_name']]
                ?? null;

            if (null === $target) {
                throw new ProfileGenerationException(sprintf('FIT message %s component references unknown target field %s.', $messageName, $component['target_name']));
            }

            $resolved[] = GeneratedComponentDefinition::create(
                targetFieldNumber: $target['field_number'],
                bits: $component['bits'],
                scale: $component['scale'],
                offset: $component['offset'],
                units: $component['units'],
                accumulated: $component['accumulated'],
                signed: $this->isSignedType(
                    typeName: $target['type_name'],
                    types: $types,
                ),
            );
        }

        return $resolved;
    }

    private function isSignedType(
        string $typeName,
        GeneratedTypes $types,
    ): bool {
        $baseType = $types->type($typeName)->baseType
            ?? $typeName;

        return in_array(
            $baseType,
            [
                'sint8',
                'sint16',
                'sint32',
                'sint64',
            ],
            true,
        );
    }

    private function assertKnownType(
        int $rowNumber,
        string $fieldName,
        string $typeName,
        GeneratedTypes $types,
    ): void {
        if (
            null === $types->type($typeName)
            && !isset(self::PRIMITIVE_FIELD_TYPES[$typeName])
        ) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d field %s references unknown type %s.', $rowNumber, $fieldName, $typeName));
        }
    }

    private function assertHeader(
        SpreadsheetRow $row,
    ): void {
        $actual = [];

        foreach (array_keys(self::EXPECTED_HEADER) as $column) {
            $actual[] = trim($row->value($column));
        }

        if (self::EXPECTED_HEADER !== $actual) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d has unexpected header %s.', $row->number, json_encode($actual, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        }
    }

    private function parseUnsignedInteger(
        string $rawValue,
        int $maximum,
        string $description,
        int $rowNumber,
        int $minimum = 0,
    ): int {
        if (1 === preg_match('/^[0-9]+$/', $rawValue)) {
            $value = filter_var(
                $rawValue,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => $minimum,
                        'max_range' => $maximum,
                    ],
                ],
            );

            if (false !== $value) {
                return $value;
            }
        }

        throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d contains unsupported %s %s.', $rowNumber, $description, $rawValue));
    }

    private function parseReferenceInteger(
        string $rawValue,
        int $rowNumber,
    ): int {
        if (1 === preg_match('/^[0-9]+$/', $rawValue)) {
            $value = filter_var(
                $rawValue,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]],
            );

            if (false !== $value) {
                return $value;
            }
        }

        if (
            1 === preg_match(
                '/^0[xX]([0-9a-fA-F]+)$/',
                $rawValue,
                $matches,
            )
        ) {
            $value = hexdec($matches[1]);

            if (is_int($value) && 0 <= $value) {
                return $value;
            }
        }

        throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d contains unsupported reference value %s.', $rowNumber, $rawValue));
    }

    private function parseOptionalNumber(
        string $rawValue,
        string $description,
        int $rowNumber,
        bool $nonZero = false,
    ): int|float|null {
        if ('' === $rawValue) {
            return null;
        }

        if (
            1 !== preg_match(
                '/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/',
                $rawValue,
            )
        ) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d contains unsupported %s %s.', $rowNumber, $description, $rawValue));
        }

        $value = str_contains($rawValue, '.')
            ? (float) $rawValue
            : (int) $rawValue;

        if (
            !is_finite((float) $value)
            || (
                $nonZero
                && 0.0 === (float) $value
            )
        ) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d contains invalid %s %s.', $rowNumber, $description, $rawValue));
        }

        return $value;
    }

    /** @return non-empty-list<string> */
    private function split(
        string $rawValue,
        string $description,
        int $rowNumber,
    ): array {
        $values = array_map(
            'trim',
            explode(',', $rawValue),
        );

        if (in_array('', $values, true)) {
            throw new ProfileGenerationException(sprintf('FIT Messages sheet row %d contains an empty %s entry.', $rowNumber, $description));
        }

        /* @var non-empty-list<string> $values */
        return $values;
    }

    private function optionalText(
        string $value,
    ): ?string {
        $value = trim($value);

        return '' === $value
            ? null
            : $value;
    }
}
