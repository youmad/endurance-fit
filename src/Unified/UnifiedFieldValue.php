<?php

declare(strict_types=1);

namespace Endurance\Fit\Unified;

interface UnifiedFieldValue
{
    public function origin(): FieldValueOrigin;

    public function fieldNumber(): int;

    public function name(): ?string;

    public function typeName(): ?string;

    public function units(): ?string;

    public function hasUsableValue(): bool;
}
