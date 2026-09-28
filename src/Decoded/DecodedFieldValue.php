<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoded;

use Youmad\Endurance\Fit\Raw\FitBaseType;

interface DecodedFieldValue
{
    public function baseType(): FitBaseType;
}
