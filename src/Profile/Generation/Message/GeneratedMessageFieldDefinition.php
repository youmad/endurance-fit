<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedMessageFieldDefinition
{
    /** @var list<GeneratedSubfieldDefinition> */
    private array $subfields;

    /** @var list<GeneratedComponentDefinition> */
    private array $components;

    /**
     * @param list<GeneratedSubfieldDefinition>  $subfields
     * @param list<GeneratedComponentDefinition> $components
     */
    private function __construct(
        public int $fieldNumber,
        public string $name,
        public string $typeName,
        public int|float|null $scale,
        public int|float|null $offset,
        public ?string $units,
        public bool $accumulated,
        array $subfields,
        array $components,
    ) {
        $this->subfields = $subfields;
        $this->components = $components;
    }

    /**
     * @param array<array-key, mixed> $subfields
     * @param array<array-key, mixed> $components
     */
    public static function create(
        int $fieldNumber,
        string $name,
        string $typeName,
        int|float|null $scale = null,
        int|float|null $offset = null,
        ?string $units = null,
        bool $accumulated = false,
        array $subfields = [],
        array $components = [],
    ): self {
        if (0 > $fieldNumber || 255 < $fieldNumber) {
            throw new ProfileGenerationException('FIT generated field number must be between 0 and 255.');
        }

        self::assertIdentifier('field name', $name);
        self::assertIdentifier('field type', $typeName);

        if (
            null !== $scale
            && (
                !is_finite((float) $scale)
                || 0.0 === (float) $scale
            )
        ) {
            throw new ProfileGenerationException(sprintf('FIT generated field %s scale must be finite and non-zero.', $name));
        }

        if (
            null !== $offset
            && !is_finite((float) $offset)
        ) {
            throw new ProfileGenerationException(sprintf('FIT generated field %s offset must be finite.', $name));
        }

        if (
            null !== $units
            && (
                '' === $units
                || trim($units) !== $units
            )
        ) {
            throw new ProfileGenerationException(sprintf('FIT generated field %s units must be a non-empty trimmed string.', $name));
        }

        $subfields = array_values($subfields);
        $subfieldNames = [];

        foreach ($subfields as $subfield) {
            if (!$subfield instanceof GeneratedSubfieldDefinition) {
                throw new ProfileGenerationException('FIT generated field subfields must contain GeneratedSubfieldDefinition objects.');
            }

            if (isset($subfieldNames[$subfield->name])) {
                throw new ProfileGenerationException(sprintf('FIT generated field %s defines subfield %s more than once.', $name, $subfield->name));
            }

            $subfieldNames[$subfield->name] = true;
        }

        /** @var list<GeneratedSubfieldDefinition> $subfields Validated above. */
        $components = array_values($components);

        foreach ($components as $component) {
            if (!$component instanceof GeneratedComponentDefinition) {
                throw new ProfileGenerationException('FIT generated field components must contain GeneratedComponentDefinition objects.');
            }
        }

        /* @var list<GeneratedComponentDefinition> $components Validated above. */
        return new self(
            fieldNumber: $fieldNumber,
            name: $name,
            typeName: $typeName,
            scale: $scale,
            offset: $offset,
            units: $units,
            accumulated: $accumulated,
            subfields: $subfields,
            components: $components,
        );
    }

    /** @return list<GeneratedSubfieldDefinition> */
    public function subfields(): array
    {
        return $this->subfields;
    }

    /** @return list<GeneratedComponentDefinition> */
    public function components(): array
    {
        return $this->components;
    }

    private static function assertIdentifier(
        string $description,
        string $value,
    ): void {
        if (1 !== preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
            throw new ProfileGenerationException(sprintf('FIT generated %s must be a snake_case identifier.', $description));
        }
    }
}
