<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Unified;

enum FieldValueOrigin: string
{
    case Physical = 'physical';
    case Component = 'component';
}
