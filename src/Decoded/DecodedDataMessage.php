<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoded;

use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\RawCompressedTimestampDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataRecord;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final readonly class DecodedDataMessage
{
    /**
     * @var list<DecodedStandardField>
     */
    private array $standardFields;

    /**
     * @var list<RawDeveloperField>
     */
    private array $developerFields;

    /**
     * @param list<DecodedStandardField> $standardFields
     * @param list<RawDeveloperField>    $developerFields
     */
    private function __construct(
        public RawDataRecord $source,
        array $standardFields,
        array $developerFields,
    ) {
        $this->standardFields = $standardFields;
        $this->developerFields = $developerFields;
    }

    /**
     * @param array<array-key, DecodedStandardField> $standardFields
     * @param array<array-key, RawDeveloperField>    $developerFields
     */
    public static function create(
        RawDataRecord $source,
        array $standardFields,
        array $developerFields,
    ): self {
        $standardFields = array_values(
            $standardFields,
        );

        $developerFields = array_values(
            $developerFields,
        );

        $expectedDefinitions = $source
            ->definition()
            ->canonicalStandardFields();

        if (
            $source
            instanceof RawCompressedTimestampDataMessage
        ) {
            $expectedPayloadDefinitions = array_values(
                array_filter(
                    $expectedDefinitions,
                    static fn (
                        StandardFieldDefinition $definition,
                    ): bool => 253 !== $definition->fieldNumber,
                ),
            );

            if (
                1 + count($expectedPayloadDefinitions)
                !== count($standardFields)
            ) {
                throw new \InvalidArgumentException('Decoded compressed FIT message must contain its header timestamp and every canonical payload field.');
            }

            $timestamp = $standardFields[0];

            if (
                253 !== $timestamp
                    ->definition
                    ->fieldNumber
                || DecodedFieldOrigin::CompressedTimestampHeader
                    !== $timestamp->origin
                || null !== $timestamp->rawField
            ) {
                throw new \InvalidArgumentException('Decoded compressed FIT message must begin with its reconstructed timestamp field.');
            }

            foreach (
                $expectedPayloadDefinitions as $index => $expectedDefinition
            ) {
                if (
                    $standardFields[$index + 1]->definition
                    !== $expectedDefinition
                ) {
                    throw new \InvalidArgumentException(sprintf('Decoded FIT payload field at position %d does not match the message definition.', $index));
                }
            }
        } else {
            if (
                count($expectedDefinitions)
                !== count($standardFields)
            ) {
                throw new \InvalidArgumentException('Decoded FIT message must contain every standard field from its definition.');
            }

            foreach (
                $expectedDefinitions as $index => $expectedDefinition
            ) {
                if (
                    $standardFields[$index]->definition
                    !== $expectedDefinition
                ) {
                    throw new \InvalidArgumentException(sprintf('Decoded FIT field at position %d does not match the message definition.', $index));
                }
            }
        }

        if (
            count($source->developerFields())
            !== count($developerFields)
        ) {
            throw new \InvalidArgumentException('Decoded FIT message must preserve every raw developer field.');
        }

        foreach (
            $source->developerFields() as $index => $sourceField
        ) {
            if ($developerFields[$index] !== $sourceField) {
                throw new \InvalidArgumentException(sprintf('Developer FIT field at position %d does not match its raw source.', $index));
            }
        }

        return new self(
            source: $source,
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }

    /**
     * @return list<DecodedStandardField>
     */
    public function standardFields(): array
    {
        return $this->standardFields;
    }

    /**
     * @return list<RawDeveloperField>
     */
    public function developerFields(): array
    {
        return $this->developerFields;
    }

    public function sequence(): int
    {
        return $this->source->sequence();
    }

    public function byteOffset(): int
    {
        return $this->source->byteOffset();
    }

    public function globalMessageNumber(): int
    {
        return $this->source->globalMessageNumber();
    }

    public function architecture(): FitArchitecture
    {
        return $this->source
            ->definition()
            ->architecture;
    }

    public function standardField(
        int $fieldNumber,
    ): ?DecodedStandardField {
        foreach ($this->standardFields as $field) {
            if ($fieldNumber === $field->definition->fieldNumber) {
                return $field;
            }
        }

        return null;
    }
}
