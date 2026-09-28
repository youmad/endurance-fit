<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

final readonly class PreferPhysicalFieldValueSelectionPolicy implements FieldValueSelectionPolicy
{
    /**
     * @return list<UnifiedFieldValue>
     */
    public function select(
        UnifiedStandardField $field,
    ): array {
        $physical = $field->physical();

        if (
            null !== $physical
            && $physical->hasUsableValue()
        ) {
            return [$physical];
        }

        if ($field->hasComponentValues()) {
            return $field->components();
        }

        return null === $physical
            ? []
            : [$physical];
    }
}
