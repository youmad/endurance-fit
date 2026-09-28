<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Type;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;
use Youmad\Endurance\Fit\Profile\Generation\ProfileIdentifierNormalizer;
use Youmad\Endurance\Fit\Profile\Generation\Xlsx\SpreadsheetRow;

final readonly class TypesSheetParser
{
    public function __construct(
        private ProfileIdentifierNormalizer $identifiers =
            new ProfileIdentifierNormalizer(),
    ) {
    }

    private const array EXPECTED_HEADER = [
        'Type Name',
        'Base Type',
        'Value Name',
        'Value',
        'Comment',
    ];

    /**
     * @param iterable<int, mixed> $rows
     */
    public function parse(iterable $rows): GeneratedTypes
    {
        $headerRead = false;

        /**
         * @var list<array{
         *     name: string,
         *     base_type: string,
         *     values: array<int, array{name: string, aliases: list<string>}>,
         *     value_order: list<int>,
         *     names: array<string, int>
         * }>
         */
        $types = [];

        /**
         * @var array{
         *     name: string,
         *     base_type: string,
         *     values: array<int, array{name: string, aliases: list<string>}>,
         *     value_order: list<int>,
         *     names: array<string, int>
         * }|null
         */
        $current = null;

        foreach ($rows as $row) {
            if (!$row instanceof SpreadsheetRow) {
                throw new ProfileGenerationException('FIT Types sheet parser expects SpreadsheetRow objects.');
            }

            if (!$headerRead) {
                $this->assertHeader($row);
                $headerRead = true;

                continue;
            }

            $sourceTypeName = trim($row->value(0));
            $baseType = strtolower(trim($row->value(1)));
            $sourceValueName = trim($row->value(2));
            $typeName = '' === $sourceTypeName
                ? ''
                : $this->identifiers->normalize($sourceTypeName);
            $valueName = '' === $sourceValueName
                ? ''
                : $this->identifiers->normalize($sourceValueName);
            $rawValue = trim($row->value(3));

            if (
                '' === $typeName
                && '' === $baseType
                && '' === $valueName
                && '' === $rawValue
            ) {
                continue;
            }

            if ('' !== $typeName) {
                if (null !== $current) {
                    $types[] = $current;
                }

                if ('' === $baseType) {
                    throw new ProfileGenerationException(sprintf('FIT Types sheet row %d starts type %s without a base type.', $row->number, $typeName));
                }

                $current = [
                    'name' => $typeName,
                    'base_type' => $baseType,
                    'values' => [],
                    'value_order' => [],
                    'names' => [],
                ];
            } elseif ('' !== $baseType) {
                throw new ProfileGenerationException(sprintf('FIT Types sheet row %d defines a base type without a type name.', $row->number));
            }

            if (
                '' === $valueName
                && '' === $rawValue
            ) {
                continue;
            }

            if (null === $current) {
                throw new ProfileGenerationException(sprintf('FIT Types sheet row %d defines a value before the first type.', $row->number));
            }

            if (
                '' === $valueName
                || '' === $rawValue
            ) {
                throw new ProfileGenerationException(sprintf('FIT Types sheet row %d must define both Value Name and Value.', $row->number));
            }

            if (isset($current['names'][$valueName])) {
                throw new ProfileGenerationException(sprintf('FIT type %s defines value name %s more than once; rows %d and %d.', $current['name'], $valueName, $current['names'][$valueName], $row->number));
            }

            $number = $this->parseInteger(
                rawValue: $rawValue,
                rowNumber: $row->number,
            );

            $current['names'][$valueName] = $row->number;

            if (!isset($current['values'][$number])) {
                $current['values'][$number] = [
                    'name' => $valueName,
                    'aliases' => [],
                ];
                $current['value_order'][] = $number;

                continue;
            }

            $current['values'][$number]['aliases'][] = $valueName;
        }

        if (!$headerRead) {
            throw new ProfileGenerationException('FIT Types sheet is empty.');
        }

        if (null !== $current) {
            $types[] = $current;
        }

        $definitions = [];

        foreach ($types as $type) {
            $values = [];

            foreach ($type['value_order'] as $number) {
                $value = $type['values'][$number];

                $values[] = GeneratedTypeValueDefinition::create(
                    value: $number,
                    name: $value['name'],
                    aliases: $value['aliases'],
                );
            }

            $definitions[] = GeneratedTypeDefinition::create(
                name: $type['name'],
                baseType: $type['base_type'],
                values: $values,
            );
        }

        return GeneratedTypes::create($definitions);
    }

    private function assertHeader(SpreadsheetRow $row): void
    {
        $actual = [];

        foreach (array_keys(self::EXPECTED_HEADER) as $column) {
            $actual[] = trim($row->value($column));
        }

        if (self::EXPECTED_HEADER !== $actual) {
            throw new ProfileGenerationException(sprintf('FIT Types sheet row %d has unexpected header %s.', $row->number, json_encode($actual, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        }
    }

    private function parseInteger(
        string $rawValue,
        int $rowNumber,
    ): int {
        if (
            1 === preg_match(
                '/^[0-9]+$/',
                $rawValue,
            )
        ) {
            $value = filter_var(
                $rawValue,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 0,
                    ],
                ],
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

            if (
                is_int($value)
                && 0 <= $value
            ) {
                return $value;
            }
        }

        throw new ProfileGenerationException(sprintf('FIT Types sheet row %d contains unsupported integer value %s.', $rowNumber, $rawValue));
    }
}
