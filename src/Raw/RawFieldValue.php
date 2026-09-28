<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidRawFieldValue;

final readonly class RawFieldValue
{
    private function __construct(
        private string $bytes,
    ) {
        if ('' === $bytes) {
            throw new InvalidRawFieldValue('Raw FIT field value cannot be empty.');
        }
    }

    public static function fromBytes(string $bytes): self
    {
        return new self($bytes);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function size(): int
    {
        return strlen($this->bytes);
    }
}
