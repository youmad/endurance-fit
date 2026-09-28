<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\IO;

use Youmad\Endurance\Fit\Exception\FitDecodeException;

final class BufferedFitInput implements FitInput
{
    private const int DEFAULT_BUFFER_SIZE = 65_536;

    private string $buffer = '';

    private int $bufferLength = 0;

    private int $bufferOffset = 0;

    private int $remaining;

    private int $position;

    public function __construct(
        private readonly FitInput $input,
        int $length,
        private readonly int $bufferSize = self::DEFAULT_BUFFER_SIZE,
    ) {
        if (0 > $length) {
            throw new FitDecodeException('Buffered FIT input length cannot be negative.');
        }

        if (0 >= $this->bufferSize) {
            throw new FitDecodeException('Buffered FIT input buffer size must be positive.');
        }

        $this->remaining = $length;
        $this->position = $input->position();
    }

    public function readExact(int $length): string
    {
        if (0 > $length) {
            throw new FitDecodeException('FIT input read length cannot be negative.');
        }

        if (0 === $length) {
            return '';
        }

        if ($length > $this->remaining) {
            throw new FitDecodeException(sprintf('Unexpected end of buffered FIT input at byte %d; expected %d more bytes.', $this->position, $length - $this->remaining));
        }

        if ($this->bufferOffset === $this->bufferLength) {
            $this->refill();
        }

        $available = $this->bufferLength - $this->bufferOffset;

        if ($length <= $available) {
            $bytes = substr(
                $this->buffer,
                $this->bufferOffset,
                $length,
            );

            $this->bufferOffset += $length;
            $this->remaining -= $length;
            $this->position += $length;

            return $bytes;
        }

        $bytes = substr(
            $this->buffer,
            $this->bufferOffset,
            $available,
        );
        $this->bufferOffset += $available;
        $this->remaining -= $available;
        $this->position += $available;

        $needed = $length - $available;

        while (0 < $needed) {
            $this->refill();

            $take = min(
                $needed,
                $this->bufferLength,
            );

            $bytes .= substr(
                $this->buffer,
                0,
                $take,
            );

            $this->bufferOffset += $take;
            $this->remaining -= $take;
            $this->position += $take;
            $needed -= $take;
        }

        return $bytes;
    }

    private function refill(): void
    {
        $this->buffer = $this->input->readExact(
            min(
                $this->bufferSize,
                $this->remaining,
            ),
        );
        $this->bufferLength = strlen($this->buffer);
        $this->bufferOffset = 0;
    }

    public function isAtEnd(): bool
    {
        return 0 === $this->remaining;
    }

    public function position(): int
    {
        return $this->position;
    }
}
