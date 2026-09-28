<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\FitInput;
use Youmad\Endurance\Fit\Raw\FitArchitecture;

final readonly class BinaryReader
{
    public function __construct(
        private FitInput $input,
    ) {
    }

    public function readByte(): int
    {
        return ord(
            $this->readBytes(1),
        );
    }

    public function readBytes(int $length): string
    {
        return $this->input->readExact($length);
    }

    public function readUInt16(
        FitArchitecture $architecture,
    ): int {
        $format = match ($architecture) {
            FitArchitecture::LittleEndian => 'vvalue',
            FitArchitecture::BigEndian => 'nvalue',
        };

        $value = unpack(
            $format,
            $this->readBytes(2),
        );

        if (
            false === $value
            || !isset($value['value'])
        ) {
            throw new FitDecodeException(sprintf('Failed to decode unsigned 16-bit integer at byte %d.', $this->position() - 2));
        }

        return $value['value'];
    }

    public function position(): int
    {
        return $this->input->position();
    }
}
