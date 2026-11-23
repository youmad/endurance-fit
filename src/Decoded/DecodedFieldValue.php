<?php

declare(strict_types=1);

namespace Endurance\Fit\Decoded;

use Endurance\Fit\Raw\FitBaseType;

interface DecodedFieldValue
{
    public function baseType(): FitBaseType;
}
