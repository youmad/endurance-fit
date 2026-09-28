<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\IO;

use Youmad\Endurance\Fit\Exception\FitDecodeException;

final class ResourceFitInput implements FitInput
{
    /**
     * @var resource
     */
    private mixed $stream;

    private int $position;

    private string $lookahead = '';

    /**
     * @param resource $stream
     */
    public function __construct(mixed $stream)
    {
        if (
            !is_resource($stream)
            || 'stream' !== get_resource_type($stream)
        ) {
            throw new FitDecodeException('FIT input must be a readable stream resource.');
        }

        $this->stream = $stream;

        $position = ftell($stream);

        $this->position = false === $position
            ? 0
            : $position;
    }

    public function readExact(int $length): string
    {
        if (0 > $length) {
            throw new FitDecodeException('FIT input read length cannot be negative.');
        }

        if (0 === $length) {
            return '';
        }

        $bytes = $this->lookahead;
        $this->lookahead = '';
        $this->position += strlen($bytes);

        while (strlen($bytes) < $length) {
            $remaining = $length - strlen($bytes);

            $chunk = fread(
                $this->stream,
                $remaining,
            );

            if (false === $chunk) {
                throw new FitDecodeException(sprintf('Failed to read FIT input at byte %d.', $this->position));
            }

            if ('' === $chunk) {
                throw new FitDecodeException(sprintf('Unexpected end of FIT input at byte %d; expected %d more bytes.', $this->position, $remaining));
            }

            $bytes .= $chunk;
            $this->position += strlen($chunk);
        }

        return $bytes;
    }

    public function isAtEnd(): bool
    {
        if ('' !== $this->lookahead) {
            return false;
        }

        $byte = fread($this->stream, 1);
        if (false === $byte || ('' === $byte && !feof($this->stream))) {
            throw new FitDecodeException(sprintf('Failed to probe FIT input at byte %d.', $this->position));
        }
        $this->lookahead = $byte;

        return '' === $byte;
    }

    public function position(): int
    {
        return $this->position;
    }
}
