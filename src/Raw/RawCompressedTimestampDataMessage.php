<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;

final readonly class RawCompressedTimestampDataMessage implements RawDataRecord
{
    private const int COMPRESSED_HEADER_MASK = 0x80;

    private const int LOCAL_MESSAGE_NUMBER_SHIFT = 5;
    private const int LOCAL_MESSAGE_NUMBER_MASK = 0x03;

    private const int TIME_OFFSET_MASK = 0x1F;

    private const int MAXIMUM_TIMESTAMP = 0xFFFFFFFF;

    public int $timeOffset;

    private function __construct(
        private int $sequenceNumber,
        private int $messageByteOffset,
        private int $recordHeaderByte,
        public MessageDefinition $definition,
        public int $reconstructedTimestamp,
        private RawDataPayload $payload,
    ) {
        $this->timeOffset = $recordHeaderByte
            & self::TIME_OFFSET_MASK;
    }

    /**
     * @param list<RawStandardField>  $standardFields
     * @param list<RawDeveloperField> $developerFields
     */
    public static function create(
        int $sequenceNumber,
        int $byteOffset,
        int $recordHeaderByte,
        MessageDefinition $definition,
        int $reconstructedTimestamp,
        array $standardFields = [],
        array $developerFields = [],
    ): self {
        if (0 > $sequenceNumber) {
            throw new InvalidRawFitMessage('Raw FIT message sequence cannot be negative.');
        }

        if (0 > $byteOffset) {
            throw new InvalidRawFitMessage('Raw FIT message byte offset cannot be negative.');
        }

        if (
            0 > $recordHeaderByte
            || 255 < $recordHeaderByte
        ) {
            throw new InvalidRawFitMessage('FIT record header must fit into one byte.');
        }

        if (
            0 === (
                $recordHeaderByte
                & self::COMPRESSED_HEADER_MASK
            )
        ) {
            throw new InvalidRawFitMessage('Compressed timestamp data message requires a compressed record header.');
        }

        $localMessageNumber = (
            $recordHeaderByte
            >> self::LOCAL_MESSAGE_NUMBER_SHIFT
        ) & self::LOCAL_MESSAGE_NUMBER_MASK;

        if (
            $localMessageNumber
            !== $definition->localMessageNumber
        ) {
            throw new InvalidRawFitMessage('Compressed timestamp record header does not match its local message number.');
        }

        if (
            0 > $reconstructedTimestamp
            || self::MAXIMUM_TIMESTAMP
            < $reconstructedTimestamp
        ) {
            throw new InvalidRawFitMessage('Reconstructed FIT timestamp must fit into an unsigned 32-bit integer.');
        }

        return new self(
            sequenceNumber: $sequenceNumber,
            messageByteOffset: $byteOffset,
            recordHeaderByte: $recordHeaderByte,
            definition: $definition,
            reconstructedTimestamp: $reconstructedTimestamp,
            payload: RawDataPayload::forCompressedTimestampMessage(
                definition: $definition,
                standardFields: $standardFields,
                developerFields: $developerFields,
            ),
        );
    }

    public function sequence(): int
    {
        return $this->sequenceNumber;
    }

    public function byteOffset(): int
    {
        return $this->messageByteOffset;
    }

    public function definition(): MessageDefinition
    {
        return $this->definition;
    }

    public function recordHeaderByte(): int
    {
        return $this->recordHeaderByte;
    }

    public function globalMessageNumber(): int
    {
        return $this->definition->globalMessageNumber;
    }

    /**
     * Returns every standard field physically present in the payload.
     * The compressed header timestamp is represented separately by
     * reconstructedTimestamp and does not alter the active definition.
     *
     * @return list<RawStandardField>
     */
    public function standardFields(): array
    {
        return $this->payload->standardFields();
    }

    /**
     * @return list<RawDeveloperField>
     */
    public function developerFields(): array
    {
        return $this->payload->developerFields();
    }
}
