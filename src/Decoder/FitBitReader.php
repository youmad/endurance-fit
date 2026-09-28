<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\FitBaseTypeKind;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Raw\RawStandardField;

final class FitBitReader
{
    private int $position = 0;

    private function __construct(
        private readonly string $bytes,
    ) {
    }

    public static function fromRawField(
        RawStandardField $field,
        FitArchitecture $architecture,
    ): self {
        return self::fromBytes(
            bytes: $field->value->bytes(),
            baseType: $field->definition->baseType,
            architecture: $architecture,
            description: sprintf(
                'FIT component container field %d',
                $field->definition->fieldNumber,
            ),
        );
    }

    public static function fromDeveloperField(
        RawDeveloperField $field,
        FitBaseType $baseType,
        FitArchitecture $architecture,
    ): self {
        return self::fromBytes(
            bytes: $field->value->bytes(),
            baseType: $baseType,
            architecture: $architecture,
            description: sprintf(
                'FIT developer component container %d:%d',
                $field->definition->developerDataIndex,
                $field->definition->fieldNumber,
            ),
        );
    }

    public static function fromUnsignedInteger(
        int $value,
        int $minimumBits,
    ): self {
        if (0 > $value) {
            throw new FitDecodeException('FIT component container integer cannot be negative.');
        }

        if (1 > $minimumBits) {
            throw new FitDecodeException('FIT component container must expose at least one bit.');
        }

        $minimumBytes = intdiv(
            $minimumBits + 7,
            8,
        );

        $bytes = '';
        $remaining = $value;

        do {
            $bytes .= chr($remaining & 0xFF);
            $remaining = intdiv($remaining, 256);
        } while (0 !== $remaining);

        if (strlen($bytes) < $minimumBytes) {
            $bytes .= str_repeat(
                "\x00",
                $minimumBytes - strlen($bytes),
            );
        }

        return new self($bytes);
    }

    private static function fromBytes(
        string $bytes,
        FitBaseType $baseType,
        FitArchitecture $architecture,
        string $description,
    ): self {
        $kind = $baseType->kind();

        if (null === $kind) {
            throw new FitDecodeException(sprintf('Cannot extract components from %s with unknown base type %d.', $description, $baseType->number()));
        }

        if (!self::supportsBitExtraction($kind)) {
            throw new FitDecodeException(sprintf('Cannot extract components from %s with base type %s.', $description, $kind->name));
        }

        $elementSize = $kind->elementSize();

        if (0 !== strlen($bytes) % $elementSize) {
            throw new FitDecodeException(sprintf('%s size %d is not divisible by element size %d.', $description, strlen($bytes), $elementSize));
        }

        $canonicalBytes = '';

        foreach (
            str_split(
                $bytes,
                $elementSize,
            ) as $elementBytes
        ) {
            $canonicalBytes .= match ($architecture) {
                FitArchitecture::LittleEndian => $elementBytes,

                FitArchitecture::BigEndian => strrev($elementBytes),
            };
        }

        return new self($canonicalBytes);
    }

    private static function supportsBitExtraction(
        FitBaseTypeKind $kind,
    ): bool {
        return match ($kind) {
            FitBaseTypeKind::StringValue,
            FitBaseTypeKind::Float32,
            FitBaseTypeKind::Float64 => false,

            default => true,
        };
    }

    public function readUnsigned(int $bits): int
    {
        if (
            1 > $bits
            || 63 < $bits
        ) {
            throw new FitDecodeException('FIT bit reader can read between 1 and 63 bits.');
        }

        if ($bits > $this->remaining()) {
            throw new FitDecodeException(sprintf('Cannot read %d FIT component bits; only %d bits remain.', $bits, $this->remaining()));
        }

        $value = 0;

        for ($index = 0; $index < $bits; ++$index) {
            $absoluteBit = $this->position + $index;
            $byteIndex = intdiv(
                $absoluteBit,
                8,
            );

            $bitIndex = $absoluteBit % 8;

            $bit = (
                ord($this->bytes[$byteIndex])
                >> $bitIndex
            ) & 0x01;

            if (0 !== $bit) {
                $value |= 1 << $index;
            }
        }

        $this->position += $bits;

        return $value;
    }

    public function readSigned(int $bits): int
    {
        $value = $this->readUnsigned($bits);
        $signBit = 1 << ($bits - 1);

        if (0 === ($value & $signBit)) {
            return $value;
        }

        return -$signBit
            + ($value & ($signBit - 1));
    }

    public function remaining(): int
    {
        return strlen($this->bytes) * 8
            - $this->position;
    }

    public function position(): int
    {
        return $this->position;
    }
}
