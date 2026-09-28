<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Component\ComponentResolvedDataMessage;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;

final readonly class TypedComponentDataMessage
{
    /**
     * @var list<TypedComponentField>
     */
    private array $components;

    /**
     * @param list<TypedComponentField> $components
     */
    private function __construct(
        public ComponentResolvedDataMessage $source,
        array $components,
    ) {
        $this->components = $components;
    }

    /**
     * @param array<array-key, TypedComponentField> $components
     */
    public static function create(
        ComponentResolvedDataMessage $source,
        array $components,
    ): self {
        $components = array_values(
            $components,
        );

        $sourceComponents = $source->components();

        if (
            count($sourceComponents)
            !== count($components)
        ) {
            throw new \InvalidArgumentException('Typed FIT component message must contain every resolved component.');
        }

        foreach (
            $sourceComponents as $index => $sourceComponent
        ) {
            if (
                $components[$index]->source
                !== $sourceComponent
            ) {
                throw new \InvalidArgumentException(sprintf('Typed FIT component at position %d does not match its resolved source.', $index));
            }
        }

        return new self(
            source: $source,
            components: $components,
        );
    }

    /**
     * @return list<TypedComponentField>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * @return list<TypedComponentField>
     */
    public function componentsForField(
        int $fieldNumber,
    ): array {
        return array_values(
            array_filter(
                $this->components,
                static fn (
                    TypedComponentField $component,
                ): bool => $fieldNumber
                    === $component->fieldNumber(),
            ),
        );
    }

    public function profiledSource(): ProfiledDataMessage
    {
        return $this->source
            ->source
            ->source;
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
        return $this->source
            ->globalMessageNumber();
    }
}
