<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoded;

use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\RawFieldValue;

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
