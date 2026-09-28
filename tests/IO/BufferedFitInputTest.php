<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\IO;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\BufferedFitInput;
use Youmad\Endurance\Fit\IO\FitInput;

final class BufferedFitInputTest extends TestCase
{
    public function testBuffersReadsWithoutLosingLogicalPositionOrReadingPastSection(): void
    {
        $input = new class('abcdefghijTRAILER', 100) implements FitInput {
            public int $readCalls = 0;

            public function __construct(
                private readonly string $bytes,
                private int $position,
            ) {
            }

            public function readExact(int $length): string
            {
                ++$this->readCalls;

                $offset = $this->position - 100;
                $bytes = substr(
                    $this->bytes,
                    $offset,
                    $length,
                );

                if (strlen($bytes) !== $length) {
                    throw new FitDecodeException('Unexpected test input end.');
                }

                $this->position += $length;

                return $bytes;
            }

            public function isAtEnd(): bool
            {
                return $this->position - 100 >= strlen($this->bytes);
            }

            public function position(): int
            {
                return $this->position;
            }
        };

        $buffered = new BufferedFitInput(
            input: $input,
            length: 10,
            bufferSize: 4,
        );

        self::assertSame(100, $buffered->position());
        self::assertSame('a', $buffered->readExact(1));
        self::assertSame(101, $buffered->position());
        self::assertSame(104, $input->position());
        self::assertSame(1, $input->readCalls);

        self::assertSame('bcd', $buffered->readExact(3));
        self::assertSame(104, $buffered->position());
        self::assertSame(104, $input->position());
        self::assertSame(1, $input->readCalls);

        self::assertSame('ef', $buffered->readExact(2));
        self::assertSame(106, $buffered->position());
        self::assertSame(108, $input->position());
        self::assertSame(2, $input->readCalls);

        self::assertSame('ghij', $buffered->readExact(4));
        self::assertSame(110, $buffered->position());
        self::assertSame(110, $input->position());
        self::assertSame(3, $input->readCalls);

        try {
            $buffered->readExact(1);
            self::fail('Expected buffered section boundary failure.');
        } catch (FitDecodeException $exception) {
            self::assertStringContainsString(
                'Unexpected end of buffered FIT input',
                $exception->getMessage(),
            );
        }

        self::assertSame(110, $buffered->position());
        self::assertSame(110, $input->position());
        self::assertSame(3, $input->readCalls);
    }

    public function testRejectsInvalidLengthAndBufferSize(): void
    {
        $input = new class implements FitInput {
            public function readExact(int $length): string
            {
                return str_repeat("\0", $length);
            }

            public function isAtEnd(): bool
            {
                return false;
            }

            public function position(): int
            {
                return 0;
            }
        };

        try {
            new BufferedFitInput(
                input: $input,
                length: -1,
            );
            self::fail('Expected negative length failure.');
        } catch (FitDecodeException $exception) {
            self::assertStringContainsString(
                'length cannot be negative',
                $exception->getMessage(),
            );
        }

        $this->expectException(FitDecodeException::class);
        $this->expectExceptionMessage('buffer size must be positive');

        new BufferedFitInput(
            input: $input,
            length: 1,
            bufferSize: 0,
        );
    }
}
