<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidFitDefinition;

final readonly class MessageDefinition
{
    private const int MINIMUM_LOCAL_MESSAGE_NUMBER = 0;
    private const int MAXIMUM_LOCAL_MESSAGE_NUMBER = 15;

    private const int MINIMUM_GLOBAL_MESSAGE_NUMBER = 0;
    private const int MAXIMUM_GLOBAL_MESSAGE_NUMBER = 65_535;

    /**
     * @var list<StandardFieldDefinition>
     */
    private array $standardFields;

    /**
     * @var list<StandardFieldDefinition>
     */
    private array $payloadStandardFields;

    /**
     * @var list<StandardFieldDefinition>
     */
    private array $canonicalStandardFields;

    /**
     * Canonical standard fields keyed by their physical payload position.
     *
     * @var array<int, StandardFieldDefinition>
     */
    private array $canonicalStandardFieldsByPayloadIndex;

    /**
     * @var list<DeveloperFieldDefinition>
     */
    private array $developerFields;

    /**
     * @var list<DeveloperFieldDefinition>
     */
    private array $payloadDeveloperFields;

    /**
     * @param list<StandardFieldDefinition>  $standardFields
     * @param list<DeveloperFieldDefinition> $developerFields
     */
    private function __construct(
        public int $localMessageNumber,
        public FitArchitecture $architecture,
        public int $globalMessageNumber,
        array $standardFields,
        array $developerFields,
    ) {
        $this->standardFields = $standardFields;
        $this->developerFields = $developerFields;

        $payloadStandardFields = [];
        $canonicalStandardFields = [];
        $canonicalStandardFieldsByPayloadIndex = [];
        $seenStandardFieldNumbers = [];

        foreach ($standardFields as $field) {
            if (0 === $field->size) {
                continue;
            }

            $payloadIndex = count($payloadStandardFields);
            $payloadStandardFields[] = $field;

            if (isset($seenStandardFieldNumbers[$field->fieldNumber])) {
                continue;
            }

            $seenStandardFieldNumbers[$field->fieldNumber] = true;
            $canonicalStandardFields[] = $field;
            $canonicalStandardFieldsByPayloadIndex[$payloadIndex] = $field;
        }

        $this->payloadStandardFields = $payloadStandardFields;
        $this->canonicalStandardFields = $canonicalStandardFields;
        $this->canonicalStandardFieldsByPayloadIndex =
            $canonicalStandardFieldsByPayloadIndex;
        $this->payloadDeveloperFields = array_values(
            array_filter(
                $developerFields,
                static fn (
                    DeveloperFieldDefinition $field,
                ): bool => 0 < $field->size,
            ),
        );
    }

    /**
     * @param array<array-key, StandardFieldDefinition>  $standardFields
     * @param array<array-key, DeveloperFieldDefinition> $developerFields
     */
    public static function create(
        int $localMessageNumber,
        FitArchitecture $architecture,
        int $globalMessageNumber,
        array $standardFields = [],
        array $developerFields = [],
    ): self {
        if (
            self::MINIMUM_LOCAL_MESSAGE_NUMBER
            > $localMessageNumber
            || self::MAXIMUM_LOCAL_MESSAGE_NUMBER
            < $localMessageNumber
        ) {
            throw new InvalidFitDefinition('Local FIT message number must be between 0 and 15.');
        }

        if (
            self::MINIMUM_GLOBAL_MESSAGE_NUMBER
            > $globalMessageNumber
            || self::MAXIMUM_GLOBAL_MESSAGE_NUMBER
            < $globalMessageNumber
        ) {
            throw new InvalidFitDefinition('Global FIT message number must fit into an unsigned 16-bit integer.');
        }

        $standardFields = array_values(
            $standardFields,
        );

        $developerFields = array_values(
            $developerFields,
        );

        return new self(
            localMessageNumber: $localMessageNumber,
            architecture: $architecture,
            globalMessageNumber: $globalMessageNumber,
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }

    /**
     * @return list<StandardFieldDefinition>
     */
    public function standardFields(): array
    {
        return $this->standardFields;
    }

    /**
     * Duplicate wire definitions with the same field number represent one
     * semantic standard field after decoding. The first payload-bearing
     * occurrence wins; every occurrence remains present in standardFields().
     *
     * @return list<StandardFieldDefinition>
     */
    public function canonicalStandardFields(): array
    {
        return $this->canonicalStandardFields;
    }

    /**
     * Standard fields that physically consume payload bytes, in wire order.
     *
     * @return list<StandardFieldDefinition>
     */
    public function payloadStandardFields(): array
    {
        return $this->payloadStandardFields;
    }

    /**
     * First payload-bearing occurrence of each semantic standard field, keyed
     * by the occurrence's physical payload position.
     *
     * @return array<int, StandardFieldDefinition>
     */
    public function canonicalStandardFieldsByPayloadIndex(): array
    {
        return $this->canonicalStandardFieldsByPayloadIndex;
    }

    /**
     * @return list<DeveloperFieldDefinition>
     */
    public function developerFields(): array
    {
        return $this->developerFields;
    }

    /**
     * Developer fields that physically consume payload bytes, in wire order.
     *
     * @return list<DeveloperFieldDefinition>
     */
    public function payloadDeveloperFields(): array
    {
        return $this->payloadDeveloperFields;
    }

    public function standardField(
        int $fieldNumber,
    ): ?StandardFieldDefinition {
        foreach ($this->standardFields as $field) {
            if ($fieldNumber === $field->fieldNumber) {
                return $field;
            }
        }

        return null;
    }
}
