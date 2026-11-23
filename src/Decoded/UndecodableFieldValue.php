<?php

declare(strict_types=1);

namespace Endurance\Fit\Decoded;

use Endurance\Fit\Raw\FitBaseType;
use Endurance\Fit\Raw\RawFieldValue;

final readonly class UndecodableFieldValue implements DecodedFieldValue
{
    public function __construct(
        private FitBaseType $type,
        public RawFieldValue $rawValue,
        public string $reason,
    ) {
    }

    public function baseType(): FitBaseType
    {
        return $this->type;
    }
}
