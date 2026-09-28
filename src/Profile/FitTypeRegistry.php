<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

interface FitTypeRegistry
{
    public function type(string $name): ?FitTypeProfile;
}
