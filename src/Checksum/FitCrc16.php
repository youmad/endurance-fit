<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Checksum;

/**
 * CRC-16/ARC with zero initial remainder and zero final XOR.
 *
 * @see https://reveng.sourceforge.io/crc-catalogue/16.htm#crc.cat.crc-16-arc
 */
final class FitCrc16
{
    // CRC-16/ARC: the reflected form of x^16 + x^15 + x^2 + 1.
    private const int REFLECTED_POLYNOMIAL = 0xA001;

    /**
     * @var list<int>|null
     */
    private static ?array $byteRemainders = null;

    private int $value = 0;

    public static function calculate(string $bytes): int
    {
        $crc = new self();
        $crc->update($bytes);

        return $crc->value();
    }

    public function update(string $bytes): void
    {
        $length = strlen($bytes);

        if (0 === $length) {
            return;
        }

        $remainders = self::$byteRemainders ??= self::generateByteRemainders();
        $remainder = $this->value;

        for ($index = 0; $index < $length; ++$index) {
            $lowByte = ($remainder ^ ord($bytes[$index])) & 0xFF;
            $remainder = ($remainder >> 8) ^ $remainders[$lowByte];
        }

        $this->value = $remainder;
    }

    public function value(): int
    {
        return $this->value;
    }

    /**
     * @return list<int>
     */
    private static function generateByteRemainders(): array
    {
        $remainders = [];

        for ($byte = 0; $byte < 256; ++$byte) {
            $remainder = $byte;

            for ($bit = 0; $bit < 8; ++$bit) {
                $outgoingBit = $remainder & 1;
                $remainder >>= 1;

                if (1 === $outgoingBit) {
                    $remainder ^= self::REFLECTED_POLYNOMIAL;
                }
            }

            $remainders[] = $remainder;
        }

        return $remainders;
    }
}
