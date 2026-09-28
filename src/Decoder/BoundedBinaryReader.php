<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Raw\FitArchitecture;

final class BoundedBinaryReader
{
    private int $remaining;

    public function __construct(
        private readonly BinaryReader $reader,
        int $length,
    ) {
        if (0 > $length) {
            throw new FitDecodeException('FIT data section size cannot be negative.');
        }

        $this->remaining = $length;
    }

    public function position(): int
    {
        return $this->reader->position();
    }

    public function readBytes(int $length): string
    {
        $this->assertAvailable($length);

        $bytes = $this->reader->readBytes($length);
        $this->remaining -= $length;

        return $bytes;
    }

    private function assertAvailable(int $length): void
    {
        if (0 > $length) {
            throw new FitDecodeException('FIT read length cannot be negative.');
        }

        if ($length > $this->remaining) {
            throw new FitDecodeException(sprintf('FIT record at byte %d exceeds the declared data section by %d bytes.', $this->position(), $length - $this->remaining));
        }
    }

    public function remaining(): int
    {
        return $this->remaining;
    }

    public function readByte(): int
    {
        $this->assertAvailable(1);

        $byte = $this->reader->readByte();
        --$this->remaining;

        return $byte;
    }

    public function readUInt16(
        FitArchitecture $architecture,
    ): int {
        $this->assertAvailable(2);

        $value = $this->reader->readUInt16(
            $architecture,
        );
        $this->remaining -= 2;

        return $value;
    }
}
