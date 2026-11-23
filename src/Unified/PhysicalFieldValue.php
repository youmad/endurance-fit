<?php

declare(strict_types=1);

namespace Endurance\Fit\Unified;

use Endurance\Fit\Decoded\DecodedFieldElements;
use Endurance\Fit\Decoded\ValidFieldElement;
use Endurance\Fit\Typed\TypedStandardField;

final readonly class PhysicalFieldValue implements UnifiedFieldValue
{
    public function __construct(
        public TypedStandardField $source,
    ) {
    }

    public function origin(): FieldValueOrigin
    {
        return FieldValueOrigin::Physical;
    }

    public function fieldNumber(): int
    {
        return $this->source->fieldNumber();
    }

    public function name(): ?string
    {
        return $this->source->name();
    }

    public function typeName(): ?string
    {
        return $this->source->typeName();
    }

    public function units(): ?string
    {
        return $this->source->units();
    }

    public function hasUsableValue(): bool
    {
        $value = $this->source
            ->source
            ->value;

        if (!$value instanceof DecodedFieldElements) {
            return false;
        }

        foreach ($value->elements() as $element) {
            if ($element instanceof ValidFieldElement) {
                return true;
            }
        }

        return false;
    }
}
