<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoded;

use Youmad\Endurance\Fit\Raw\FitBaseType;

final readonly class DecodedFieldElements implements DecodedFieldValue
{
    /**
     * @var non-empty-list<DecodedFieldElement>
     */
    private array $elements;

    /**
     * @param non-empty-list<DecodedFieldElement> $elements
     */
    private function __construct(
        private FitBaseType $type,
        array $elements,
    ) {
        $this->elements = $elements;
    }

    public static function create(
        FitBaseType $baseType,
        DecodedFieldElement ...$elements,
    ): self {
        if ([] === $elements) {
            throw new \InvalidArgumentException('Decoded FIT field must contain at least one element.');
        }

        return new self(
            type: $baseType,
            elements: $elements,
        );
    }

    public function baseType(): FitBaseType
    {
        return $this->type;
    }

    /**
     * @return non-empty-list<DecodedFieldElement>
     */
    public function elements(): array
    {
        return $this->elements;
    }
}
