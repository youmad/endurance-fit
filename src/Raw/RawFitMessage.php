<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

interface RawFitMessage
{
    public function sequence(): int;

    public function byteOffset(): int;
}
