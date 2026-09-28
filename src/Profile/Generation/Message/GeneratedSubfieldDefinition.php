<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedSubfieldDefinition
{
    /** @var non-empty-list<GeneratedSubfieldConditionDefinition> */
    private array $conditions;

    /** @var list<GeneratedComponentDefinition> */
    private array $components;

    /**
     * @param non-empty-list<GeneratedSubfieldConditionDefinition> $conditions
     * @param list<GeneratedComponentDefinition>                   $components
     */
    private function __construct(
        public string $name,
        public string $typeName,
        public int|float|null $scale,
        public int|float|null $offset,
        public ?string $units,
        array $conditions,
        array $components,
    ) {
        $this->conditions = $conditions;
        $this->components = $components;
    }

    /**
     * @param array<array-key, mixed> $conditions
     * @param array<array-key, mixed> $components
     */
    public static function create(
        string $name,
        string $typeName,
        int|float|null $scale = null,
        int|float|null $offset = null,
        ?string $units = null,
        array $conditions = [],
        array $components = [],
    ): self {
        self::assertIdentifier('subfield name', $name);
        self::assertIdentifier('subfield type', $typeName);

        if (
            null !== $scale
            && (
                !is_finite((float) $scale)
                || 0.0 === (float) $scale
            )
        ) {
            throw new ProfileGenerationException(sprintf('FIT generated subfield %s scale must be finite and non-zero.', $name));
        }

        if (
            null !== $offset
            && !is_finite((float) $offset)
        ) {
            throw new ProfileGenerationException(sprintf('FIT generated subfield %s offset must be finite.', $name));
        }

        if (
            null !== $units
            && (
                '' === $units
                || trim($units) !== $units
            )
        ) {
            throw new ProfileGenerationException(sprintf('FIT generated subfield %s units must be a non-empty trimmed string.', $name));
        }

        $conditions = array_values($conditions);

        if ([] === $conditions) {
            throw new ProfileGenerationException(sprintf('FIT generated subfield %s must define at least one condition.', $name));
        }

        $referenceFields = [];

        foreach ($conditions as $condition) {
            if (!$condition instanceof GeneratedSubfieldConditionDefinition) {
                throw new ProfileGenerationException('FIT generated subfield conditions must contain GeneratedSubfieldConditionDefinition objects.');
            }

            if (isset($referenceFields[$condition->referenceFieldNumber])) {
                throw new ProfileGenerationException(sprintf('FIT generated subfield %s defines reference field %d more than once.', $name, $condition->referenceFieldNumber));
            }

            $referenceFields[$condition->referenceFieldNumber] = true;
        }

        /** @var non-empty-list<GeneratedSubfieldConditionDefinition> $conditions Validated above. */
        $components = array_values($components);

        foreach ($components as $component) {
            if (!$component instanceof GeneratedComponentDefinition) {
                throw new ProfileGenerationException('FIT generated subfield components must contain GeneratedComponentDefinition objects.');
            }
        }

        /* @var list<GeneratedComponentDefinition> $components Validated above. */
        /* @var non-empty-list<GeneratedSubfieldConditionDefinition> $conditions */
        return new self(
            name: $name,
            typeName: $typeName,
            scale: $scale,
            offset: $offset,
            units: $units,
            conditions: $conditions,
            components: $components,
        );
    }

    /** @return non-empty-list<GeneratedSubfieldConditionDefinition> */
    public function conditions(): array
    {
        return $this->conditions;
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
