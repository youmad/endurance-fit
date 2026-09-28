<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

enum FitBaseTypeKind: int
{
    case Enumeration = 0x00;
    case SignedInt8 = 0x01;
    case UnsignedInt8 = 0x02;
    case SignedInt16 = 0x03;
    case UnsignedInt16 = 0x04;
    case SignedInt32 = 0x05;
    case UnsignedInt32 = 0x06;
    case StringValue = 0x07;
    case Float32 = 0x08;
    case Float64 = 0x09;
    case UnsignedInt8Zero = 0x0A;
    case UnsignedInt16Zero = 0x0B;
    case UnsignedInt32Zero = 0x0C;
    case Byte = 0x0D;
    case SignedInt64 = 0x0E;
    case UnsignedInt64 = 0x0F;
    case UnsignedInt64Zero = 0x10;

    public function elementSize(): int
    {
        return match ($this) {
            self::Enumeration,
            self::SignedInt8,
            self::UnsignedInt8,
            self::StringValue,
            self::UnsignedInt8Zero,
            self::Byte => 1,

            self::SignedInt16,
            self::UnsignedInt16,
            self::UnsignedInt16Zero => 2,

            self::SignedInt32,
            self::UnsignedInt32,
            self::Float32,
            self::UnsignedInt32Zero => 4,

            self::Float64,
            self::SignedInt64,
            self::UnsignedInt64,
            self::UnsignedInt64Zero => 8,
        };
    }
}
