<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Checksum\FitCrc16;
use Youmad\Endurance\Fit\Exception\InvalidFitFile;
use Youmad\Endurance\Fit\IO\BufferedFitInput;
use Youmad\Endurance\Fit\IO\CrcTrackingFitInput;
use Youmad\Endurance\Fit\IO\FitInput;
use Youmad\Endurance\Fit\Raw\FitFileHeader;

final readonly class FitFileReader
{
    private const int MINIMUM_HEADER_SIZE = 12;
    private const int MAXIMUM_SUPPORTED_PROTOCOL_MAJOR_VERSION = 2;

    public function __construct(
        private RawRecordStreamReader $records = new RawRecordStreamReader(),
    ) {
    }

    public function open(
        FitInput $input,
    ): RawFitFileStream {
        // Garmin Decode treats zero bytes before a header as padding. Once
        // a member starts, its declared size and CRC remain strict.
        do {
            $byteOffset = $input->position();
            $headerSizeByte = $input->readExact(1);
            $headerSize = ord($headerSizeByte);
        } while (0 === $headerSize);

        if (self::MINIMUM_HEADER_SIZE > $headerSize) {
            throw new InvalidFitFile(sprintf('Invalid FIT file header size %d; minimum is %d bytes.', $headerSize, self::MINIMUM_HEADER_SIZE));
        }

        $headerBytes = $headerSizeByte
            .$input->readExact(
                $headerSize - 1,
            );

        $header = FitFileHeader::fromBytes(
            byteOffset: $byteOffset,
            bytes: $headerBytes,
        );

        $this->assertSupportedProtocolVersion($header);
        $this->assertHeaderCrc($header);

        $fileCrc = new FitCrc16();
        $fileCrc->update($headerBytes);

        return new RawFitFileStream(
            header: $header,
            input: $input,
            dataInput: new BufferedFitInput(
                input: new CrcTrackingFitInput(
                    input: $input,
                    crc: $fileCrc,
                ),
                length: $header->dataSize,
            ),
            fileCrc: $fileCrc,
            records: $this->records,
        );
    }

    private function assertSupportedProtocolVersion(
        FitFileHeader $header,
    ): void {
        $majorVersion = $header->protocolMajorVersion();

        if (
            self::MAXIMUM_SUPPORTED_PROTOCOL_MAJOR_VERSION
            < $majorVersion
        ) {
            throw new InvalidFitFile(sprintf('Unsupported FIT protocol major version %d; maximum supported major version is %d.', $majorVersion, self::MAXIMUM_SUPPORTED_PROTOCOL_MAJOR_VERSION));
        }
    }

    private function assertHeaderCrc(
        FitFileHeader $header,
    ): void {
        $declaredCrc = $header->headerCrc;

        if (
            null === $declaredCrc
            || 0 === $declaredCrc
        ) {
            return;
        }

        $calculatedCrc = FitCrc16::calculate(
            substr(
                $header->rawBytes(),
                0,
                12,
            ),
        );

        if ($declaredCrc !== $calculatedCrc) {
            throw new InvalidFitFile(sprintf('FIT header CRC mismatch: declared 0x%04X, calculated 0x%04X.', $declaredCrc, $calculatedCrc));
        }
    }
}
