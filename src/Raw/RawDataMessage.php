<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;

final readonly class RawDataMessage implements RawDataRecord
{
    private const int COMPRESSED_HEADER_MASK = 0x80;
    private const int DEFINITION_MESSAGE_MASK = 0x40;
    private const int LOCAL_MESSAGE_NUMBER_MASK = 0x0F;

    private function __construct(
        private int $sequenceNumber,
        private int $messageByteOffset,
        private int $recordHeaderByte,
        public MessageDefinition $definition,
        private RawDataPayload $payload,
    ) {
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
            0 !== (
                $recordHeaderByte
                & self::COMPRESSED_HEADER_MASK
            )
            || 0 !== (
                $recordHeaderByte
                & self::DEFINITION_MESSAGE_MASK
            )
        ) {
            throw new InvalidRawFitMessage('Raw data message requires a normal data record header.');
        }

        if (
            (
                $recordHeaderByte
                & self::LOCAL_MESSAGE_NUMBER_MASK
            )
            !== $definition->localMessageNumber
        ) {
            throw new InvalidRawFitMessage('Data record header does not match its local message number.');
        }

        return new self(
            sequenceNumber: $sequenceNumber,
            messageByteOffset: $byteOffset,
            recordHeaderByte: $recordHeaderByte,
            definition: $definition,
            payload: RawDataPayload::forNormalMessage(
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
