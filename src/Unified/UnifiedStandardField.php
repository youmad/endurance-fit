<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

final readonly class UnifiedStandardField
{
    /**
     * @var list<ComponentFieldValue>
     */
    private array $components;

    /**
     * @param list<ComponentFieldValue> $components
     */
    private function __construct(
        public int $fieldNumber,
        private ?PhysicalFieldValue $physical,
        array $components,
    ) {
        $this->components = $components;
    }

    /**
     * @param array<array-key, ComponentFieldValue> $components
     */
    public static function create(
        ?PhysicalFieldValue $physical,
        array $components = [],
    ): self {
        $components = array_values(
            $components,
        );

        if (
            null === $physical
            && [] === $components
        ) {
            throw new \InvalidArgumentException('Unified FIT field must contain a physical or component-derived value.');
        }

        $fieldNumber = $physical?->fieldNumber()
            ?? $components[0]->fieldNumber();

        foreach ($components as $component) {
            if ($fieldNumber !== $component->fieldNumber()) {
                throw new \InvalidArgumentException('Unified FIT field values must share the same field number.');
            }
        }

        return new self(
            fieldNumber: $fieldNumber,
            physical: $physical,
            components: $components,
        );
    }

    /**
     * @param list<ComponentFieldValue> $components
     *
     * @internal
     */
    public static function fromPipeline(
        int $fieldNumber,
        ?PhysicalFieldValue $physical,
        array $components = [],
    ): self {
        return new self(
            fieldNumber: $fieldNumber,
            physical: $physical,
            components: $components,
        );
    }

    public function physical(): ?PhysicalFieldValue
    {
        return $this->physical;
    }

    /**
     * @return list<ComponentFieldValue>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * Physical payload is listed first, but no value is discarded.
     *
     * @return non-empty-list<UnifiedFieldValue>
     */
    public function values(): array
    {
        if (null === $this->physical) {
            return $this->components;
        }

        return [
            $this->physical,
            ...$this->components,
        ];
    }

    public function hasPhysicalValue(): bool
    {
        return null !== $this->physical;
    }

    public function hasComponentValues(): bool
    {
        return [] !== $this->components;
    }

    public function name(): ?string
    {
        if (null !== $this->physical) {
            return $this->physical->name();
        }

        return $this->components[0]->name();
    }

    public function typeName(): ?string
    {
        if (null !== $this->physical) {
            return $this->physical->typeName();
        }

        return $this->components[0]->typeName();
    }

    public function units(): ?string
    {
        if (null !== $this->physical) {
            return $this->physical->units();
        }

        return $this->components[0]->units();
    }
}
