<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

interface FitProfileRegistry
{
    public function message(
        int $globalMessageNumber,
    ): ?MessageProfile;
}
