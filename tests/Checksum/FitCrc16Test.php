<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Checksum;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Checksum\FitCrc16;

final class FitCrc16Test extends TestCase
{
    public function testMatchesCrc16ArcCheckValue(): void
    {
        self::assertSame(0xBB3D, FitCrc16::calculate('123456789'));
    }

    public function testEmptyUpdatesPreserveTheCurrentRemainder(): void
    {
        $crc = new FitCrc16();

        self::assertSame(0, $crc->value());
        self::assertSame(0, FitCrc16::calculate(''));

        $crc->update('');
        self::assertSame(0, $crc->value());

        $crc->update('123456789');
        $crc->update('');
        self::assertSame(0xBB3D, $crc->value());
    }

    public function testMatchesPolynomialDivisionForEveryTwoByteInput(): void
    {
        for ($first = 0; $first < 256; ++$first) {
            for ($second = 0; $second < 256; ++$second) {
                $bytes = chr($first).chr($second);

                self::assertSame(
                    $this->polynomialRemainder($bytes),
                    FitCrc16::calculate($bytes),
                    bin2hex($bytes),
                );
            }
        }
    }

    public function testEverySplitOfBinaryPayloadPreservesChecksum(): void
    {
        $bytes = '';

        for ($byte = 0; $byte < 256; ++$byte) {
            $bytes .= chr($byte);
        }

        $expected = $this->polynomialRemainder($bytes);

        for ($split = 0; $split <= strlen($bytes); ++$split) {
            $crc = new FitCrc16();
            $crc->update(substr($bytes, 0, $split));
            $crc->update('');
            $crc->update(substr($bytes, $split));

            self::assertSame($expected, $crc->value());
        }

        $crc = new FitCrc16();

        foreach (str_split($bytes) as $byte) {
            $crc->update($byte);
        }

        self::assertSame($expected, $crc->value());
        self::assertSame(0, FitCrc16::calculate($bytes.pack('v', $expected)));
    }

    public function testInterleavedInstancesKeepSeparateState(): void
    {
        $first = new FitCrc16();
        $second = new FitCrc16();

        $first->update('1234');
        $second->update("\x00\xFF");
        self::assertSame(0xBB3D, FitCrc16::calculate('123456789'));
        $first->update('56789');
        $second->update("\x80\x01");

        self::assertSame(0xBB3D, $first->value());
        self::assertSame(
            $this->polynomialRemainder("\x00\xFF\x80\x01"),
            $second->value(),
        );
    }

    public function testCalculatesOfficialHeaderExampleCrc(): void
    {
        $bytes = hex2bin(
            '0E104308780609002E464954',
        );

        self::assertIsString($bytes);

        self::assertSame(
            0x8596,
            FitCrc16::calculate($bytes),
        );
    }

    public function testCanCalculateCrcIncrementally(): void
    {
        $crc = new FitCrc16();

        $crc->update("\x0E\x10\x43\x08");
        $crc->update("\x78\x06\x09\x00");
        $crc->update("\x2E\x46\x49\x54");

        self::assertSame(
            0x8596,
            $crc->value(),
        );
    }

    public function testHeaderIncludingItsCrcHasZeroRemainder(): void
    {
        $bytes = hex2bin(
            '0E104308780609002E4649549685',
        );

        self::assertIsString($bytes);

        self::assertSame(
            0,
            FitCrc16::calculate($bytes),
        );
    }

    /** Evaluate the normal polynomial bit by bit, without a lookup table. */
    private function polynomialRemainder(string $bytes): int
    {
        $remainder = 0;

        foreach (str_split($bytes) as $byte) {
            $value = ord($byte);

            // ARC consumes the least significant input bit first.
            for ($bit = 0; $bit < 8; ++$bit) {
                $feedback = (($remainder >> 15) ^ ($value >> $bit)) & 1;
                $remainder = ($remainder << 1) & 0xFFFF;

                if (1 === $feedback) {
                    $remainder ^= 0x8005;
                }
            }
        }

        $reflected = 0;

        for ($bit = 0; $bit < 16; ++$bit) {
            $reflected = ($reflected << 1) | (($remainder >> $bit) & 1);
        }

        return $reflected;
    }
}
