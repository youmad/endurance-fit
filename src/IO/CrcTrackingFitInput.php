<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\IO;

use Youmad\Endurance\Fit\Checksum\FitCrc16;

final readonly class CrcTrackingFitInput implements FitInput
{
    public function __construct(
        private FitInput $input,
        private FitCrc16 $crc,
    ) {
    }

    public function readExact(int $length): string
    {
        $bytes = $this->input->readExact($length);

        $this->crc->update($bytes);

        return $bytes;
    }

    public function isAtEnd(): bool
    {
        return $this->input->isAtEnd();
    }

    public function position(): int
    {
        return $this->input->position();
    }
}
