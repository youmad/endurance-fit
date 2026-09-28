<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidFitFile;

final readonly class FitFileHeader
{
    private const int MINIMUM_HEADER_SIZE = 12;
    private const int HEADER_WITH_CRC_SIZE = 14;
    private const string DATA_TYPE = '.FIT';

    private function __construct(
        public int $byteOffset,
        public int $headerSize,
        public int $protocolVersion,
        public int $profileVersion,
        public int $dataSize,
        public string $dataType,
        public ?int $headerCrc,
        private string $rawBytes,
    ) {
    }

    public static function fromBytes(
        int $byteOffset,
        string $bytes,
    ): self {
        if (0 > $byteOffset) {
            throw new InvalidFitFile('FIT file header offset cannot be negative.');
        }

        if ('' === $bytes) {
            throw new InvalidFitFile('FIT file header cannot be empty.');
        }

        $headerSize = ord($bytes[0]);

        if (self::MINIMUM_HEADER_SIZE > $headerSize) {
            throw new InvalidFitFile(sprintf('Invalid FIT file header size %d; minimum is %d bytes.', $headerSize, self::MINIMUM_HEADER_SIZE));
        }

        if ($headerSize !== strlen($bytes)) {
            throw new InvalidFitFile(sprintf('FIT file header declares %d bytes, %d bytes provided.', $headerSize, strlen($bytes)));
        }

        $profileVersion = self::decodeUInt16(
            substr($bytes, 2, 2),
        );

        $dataSize = self::decodeUInt32(
            substr($bytes, 4, 4),
        );

        if (0 === $dataSize) {
            throw new InvalidFitFile('FIT data section cannot be empty.');
        }

        $dataType = substr(
            $bytes,
            8,
            4,
        );

        if (self::DATA_TYPE !== $dataType) {
            throw new InvalidFitFile(sprintf('Invalid FIT data type signature %s.', bin2hex($dataType)));
        }

        $headerCrc = self::HEADER_WITH_CRC_SIZE
        === $headerSize
            ? self::decodeUInt16(
                substr($bytes, 12, 2),
            )
            : null;

        return new self(
            byteOffset: $byteOffset,
            headerSize: $headerSize,
            protocolVersion: ord($bytes[1]),
            profileVersion: $profileVersion,
            dataSize: $dataSize,
            dataType: $dataType,
            headerCrc: $headerCrc,
            rawBytes: $bytes,
        );
    }

    private static function decodeUInt16(
        string $bytes,
    ): int {
        $value = unpack(
            'vvalue',
            $bytes,
        );

        if (
            false === $value
            || !isset($value['value'])
        ) {
            throw new InvalidFitFile('Failed to decode FIT unsigned 16-bit header value.');
        }

        return $value['value'];
    }

    private static function decodeUInt32(
        string $bytes,
    ): int {
        $value = unpack(
            'Vvalue',
            $bytes,
        );

        if (
            false === $value
            || !isset($value['value'])
        ) {
            throw new InvalidFitFile('Failed to decode FIT unsigned 32-bit header value.');
        }

        return $value['value'];
    }

    public function protocolMajorVersion(): int
    {
        return (
            $this->protocolVersion >> 4
        ) & 0x0F;
    }

    public function protocolMinorVersion(): int
    {
        return $this->protocolVersion & 0x0F;
    }

    public function rawBytes(): string
    {
        return $this->rawBytes;
    }
}
