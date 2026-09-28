<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\IO;

interface FitInput
{
    public function readExact(int $length): string;

    /** Probe EOF without changing the logical position; read errors must throw. */
    public function isAtEnd(): bool;

    public function position(): int;
}
