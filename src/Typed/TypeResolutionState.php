<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

enum TypeResolutionState: string
{
    case Applied = 'applied';
    case NotApplicable = 'not_applicable';
    case Unavailable = 'unavailable';
}
