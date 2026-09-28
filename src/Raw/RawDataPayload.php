<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;

final readonly class RawDataPayload
{
    /**
     * @var list<RawStandardField>
     */
    private array $standardFields;

    /**
     * @var list<RawDeveloperField>
     */
    private array $developerFields;

    /**
     * @param list<RawStandardField>  $standardFields
     * @param list<RawDeveloperField> $developerFields
     */
    private function __construct(
        array $standardFields,
        array $developerFields,
    ) {
        $this->standardFields = $standardFields;
        $this->developerFields = $developerFields;
    }

    /**
     * @param array<array-key, RawStandardField>  $standardFields
     * @param array<array-key, RawDeveloperField> $developerFields
     */
    public static function forNormalMessage(
        MessageDefinition $definition,
        array $standardFields = [],
        array $developerFields = [],
    ): self {
        return self::fromExpectedDefinitions(
            expectedStandardFields: $definition->payloadStandardFields(),
            expectedDeveloperFields: $definition->payloadDeveloperFields(),
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }

    /**
     * @param list<StandardFieldDefinition>       $expectedStandardFields
     * @param list<DeveloperFieldDefinition>      $expectedDeveloperFields
     * @param array<array-key, RawStandardField>  $standardFields
     * @param array<array-key, RawDeveloperField> $developerFields
     */
    private static function fromExpectedDefinitions(
        array $expectedStandardFields,
        array $expectedDeveloperFields,
        array $standardFields,
        array $developerFields,
    ): self {
        $standardFields = array_values(
            $standardFields,
        );

        $developerFields = array_values(
            $developerFields,
        );

        self::assertStandardFieldsMatch(
            expected: $expectedStandardFields,
            actual: $standardFields,
        );

        self::assertDeveloperFieldsMatch(
            expected: $expectedDeveloperFields,
            actual: $developerFields,
        );

        return new self(
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }

    /**
     * @param list<StandardFieldDefinition> $expected
     * @param list<RawStandardField>        $actual
     */
    private static function assertStandardFieldsMatch(
        array $expected,
        array $actual,
    ): void {
        if (count($expected) !== count($actual)) {
            throw new InvalidRawFitMessage(sprintf('Raw FIT payload expects %d standard fields, %d fields provided.', count($expected), count($actual)));
        }

        foreach ($expected as $index => $expectedDefinition) {
            if (
                $actual[$index]->definition
                !== $expectedDefinition
            ) {
                throw new InvalidRawFitMessage(sprintf('Standard FIT field at payload position %d does not match the active definition.', $index));
            }
        }
    }

    /**
     * @param list<DeveloperFieldDefinition> $expected
     * @param list<RawDeveloperField>        $actual
     */
    private static function assertDeveloperFieldsMatch(
        array $expected,
        array $actual,
    ): void {
        if (count($expected) !== count($actual)) {
            throw new InvalidRawFitMessage(sprintf('Raw FIT payload expects %d developer fields, %d fields provided.', count($expected), count($actual)));
        }

        foreach ($expected as $index => $expectedDefinition) {
            if (
                $actual[$index]->definition
                !== $expectedDefinition
            ) {
                throw new InvalidRawFitMessage(sprintf('Developer FIT field at payload position %d does not match the active definition.', $index));
            }
        }
    }

    /**
     * @return list<RawStandardField>
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

    /**
     * @param array<array-key, RawStandardField>  $standardFields
     * @param array<array-key, RawDeveloperField> $developerFields
     */
    public static function forCompressedTimestampMessage(
        MessageDefinition $definition,
        array $standardFields = [],
        array $developerFields = [],
    ): self {
        return self::fromExpectedDefinitions(
            expectedStandardFields: $definition->payloadStandardFields(),
            expectedDeveloperFields: $definition->payloadDeveloperFields(),
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }
}
