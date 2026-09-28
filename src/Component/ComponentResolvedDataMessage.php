<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Component;

final readonly class ComponentResolvedDataMessage
{
    /**
     * @var list<ResolvedComponentField>
     */
    private array $components;

    /**
     * @param list<ResolvedComponentField> $components
     */
    private function __construct(
        public ComponentExtractedDataMessage $source,
        array $components,
    ) {
        $this->components = $components;
    }

    /**
     * @param array<array-key, ResolvedComponentField> $components
     */
    public static function create(
        ComponentExtractedDataMessage $source,
        array $components,
    ): self {
        $components = array_values(
            $components,
        );

        $extracted = $source->components();

        if (count($extracted) !== count($components)) {
            throw new \InvalidArgumentException('Resolved FIT message must contain every extracted component.');
        }

        foreach (
            $extracted as $index => $extractedComponent
        ) {
            if (
                $components[$index]->source
                !== $extractedComponent
            ) {
                throw new \InvalidArgumentException(sprintf('Resolved FIT component at position %d does not match its extracted source.', $index));
            }
        }

        return new self(
            source: $source,
            components: $components,
        );
    }

    /**
     * @return list<ResolvedComponentField>
     */
    public function components(): array
    {
        return $this->components;
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

    /**
     * @return list<ResolvedComponentField>
     */
    public function componentsForField(
        int $fieldNumber,
    ): array {
        return array_values(
            array_filter(
                $this->components,
                static fn (
                    ResolvedComponentField $component,
                ): bool => $fieldNumber
                    === $component->targetFieldNumber(),
            ),
        );
    }
}
