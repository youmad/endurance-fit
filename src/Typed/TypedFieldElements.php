<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

final readonly class TypedFieldElements implements TypedFieldValue
{
    /**
     * @var non-empty-list<TypedFieldElement>
     */
    private array $elements;

    /**
     * @param non-empty-list<TypedFieldElement> $elements
     */
    private function __construct(array $elements)
    {
        $this->elements = $elements;
    }

    public static function create(
        TypedFieldElement ...$elements,
    ): self {
        if ([] === $elements) {
            throw new \InvalidArgumentException('Typed FIT field must contain at least one element.');
        }

        return new self(
            array_values($elements),
        );
    }

    /**
     * @return non-empty-list<TypedFieldElement>
     */
    public function elements(): array
    {
        return $this->elements;
    }
}
