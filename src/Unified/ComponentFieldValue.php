<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

use Youmad\Endurance\Fit\Typed\TypedComponentField;

final readonly class ComponentFieldValue implements UnifiedFieldValue
{
    public function __construct(
        public TypedComponentField $source,
    ) {
    }

    public function origin(): FieldValueOrigin
    {
        return FieldValueOrigin::Component;
    }

    public function fieldNumber(): int
    {
        return $this->source->fieldNumber();
    }

    public function name(): string
    {
        return $this->source->name();
    }

    public function typeName(): string
    {
        return $this->source->typeName();
    }

    public function units(): ?string
    {
        return $this->source->units();
    }

    public function hasUsableValue(): bool
    {
        return true;
    }
}
