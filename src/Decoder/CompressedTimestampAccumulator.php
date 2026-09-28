<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\RawDataRecord;

final class CompressedTimestampAccumulator
{
    private const int TIMESTAMP_FIELD_NUMBER = 253;
    private const int TIMESTAMP_FIELD_SIZE = 4;
    private const int TIMESTAMP_BASE_TYPE_NUMBER = 0x06;

    private const int TIME_OFFSET_MASK = 0x1F;

    private const int INVALID_TIMESTAMP = 0xFFFFFFFF;
    private const int MAXIMUM_TIMESTAMP = 0xFFFFFFFF;
    private const int UINT32_RANGE = 0x100000000;

    private int $timestamp = 0;
    private int $lastTimeOffset = 0;

    /** Garmin nextFile() retains the timestamp but resets its offset state. */
    public function beginNextFile(): void
    {
        $this->lastTimeOffset = 0;
    }

    public function observe(
        RawDataRecord $message,
    ): void {
        // standardFields() contains physical payload fields only, including
        // on compressed messages. A valid payload timestamp reseeds both
        // values; reconstructedTimestamp is stored separately and is not
        // encountered here. Missing/invalid payload timestamps leave the
        // compressed header's state intact.
        foreach (
            $message->standardFields() as $field
        ) {
            $definition = $field->definition;

            if (
                self::TIMESTAMP_FIELD_NUMBER
                !== $definition->fieldNumber
            ) {
                continue;
            }

            if (
                self::TIMESTAMP_FIELD_SIZE
                !== $definition->size
                || self::TIMESTAMP_BASE_TYPE_NUMBER
                !== $definition->baseType->number()
            ) {
                continue;
            }

            $timestamp = $this->decodeTimestamp(
                architecture: $message
                    ->definition()
                    ->architecture,
                bytes: $field->value->bytes(),
            );

            if (
                self::INVALID_TIMESTAMP
                === $timestamp
            ) {
                continue;
            }

            $this->timestamp = $timestamp;
            $this->lastTimeOffset = $timestamp & self::TIME_OFFSET_MASK;
        }
    }

    private function decodeTimestamp(
        FitArchitecture $architecture,
        string $bytes,
    ): int {
        $format = match ($architecture) {
            FitArchitecture::LittleEndian => 'Vvalue',
            FitArchitecture::BigEndian => 'Nvalue',
        };

        $value = unpack(
            $format,
            $bytes,
        );

        if (
            false === $value
            || !isset($value['value'])
        ) {
            throw new FitDecodeException('Failed to decode full FIT timestamp.');
        }

        return $value['value'];
    }

    public function reconstruct(
        int $timeOffset,
        int $byteOffset,
    ): int {
        if (
            0 > $timeOffset
            || self::TIME_OFFSET_MASK < $timeOffset
        ) {
            throw new FitDecodeException(sprintf('Compressed FIT timestamp offset at byte %d must be between 0 and 31.', $byteOffset));
        }

        $timestamp = $this->timestamp
            + (($timeOffset - $this->lastTimeOffset) & self::TIME_OFFSET_MASK);
        $this->lastTimeOffset = $timeOffset;

        if (
            self::MAXIMUM_TIMESTAMP
            < $timestamp
        ) {
            $timestamp -= self::UINT32_RANGE;
        }

        $this->timestamp = $timestamp;

        return $timestamp;
    }
}
