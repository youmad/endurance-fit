<?php

declare(strict_types=1);

namespace Endurance\Fit\Raw;

enum FitArchitecture: int
{
    case LittleEndian = 0;
    case BigEndian = 1;
}
