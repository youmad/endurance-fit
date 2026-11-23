<?php

declare(strict_types=1);

namespace Endurance\Fit\Profiled;

enum ProfileNormalizationState: string
{
    case Applied = 'applied';
    case NotRequired = 'not_required';
    case Unavailable = 'unavailable';
}
