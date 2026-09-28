<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

interface FieldValueSelectionPolicy
{
    /**
     * @return list<UnifiedFieldValue>
     */
    public function select(
        UnifiedStandardField $field,
    ): array;
}
