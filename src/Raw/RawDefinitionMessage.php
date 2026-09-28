<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidRawFitMessage;

final readonly class RawDefinitionMessage implements RawFitMessage
{
    private const int COMPRESSED_HEADER_MASK = 0x80;
    private const int DEFINITION_MESSAGE_MASK = 0x40;
    private const int DEVELOPER_DATA_MASK = 0x20;
    private const int LOCAL_MESSAGE_NUMBER_MASK = 0x0F;

    public function __construct(
        private int $sequenceNumber,
        private int $messageByteOffset,
        private int $recordHeaderByte,
        public int $reservedByte,
        public MessageDefinition $definition,
    ) {
        if (0 > $sequenceNumber) {
            throw new InvalidRawFitMessage('Raw FIT message sequence cannot be negative.');
        }

        if (0 > $messageByteOffset) {
            throw new InvalidRawFitMessage('Raw FIT message byte offset cannot be negative.');
        }

        self::assertByte(
            'FIT record header',
            $recordHeaderByte,
        );

        self::assertByte(
            'FIT definition reserved value',
            $reservedByte,
        );

        if (
            0 !== (
                $recordHeaderByte
                & self::COMPRESSED_HEADER_MASK
            )
            || 0 === (
                $recordHeaderByte
                & self::DEFINITION_MESSAGE_MASK
            )
        ) {
            throw new InvalidRawFitMessage('Raw definition message requires a normal definition record header.');
        }

        if (
            (
                $recordHeaderByte
                & self::LOCAL_MESSAGE_NUMBER_MASK
            )
            !== $definition->localMessageNumber
        ) {
            throw new InvalidRawFitMessage('Definition record header does not match its local message number.');
        }

        if (
            [] !== $definition->developerFields()
            && 0 === (
                $recordHeaderByte
                & self::DEVELOPER_DATA_MASK
            )
        ) {
            throw new InvalidRawFitMessage('Definition containing developer fields must set the developer data flag.');
        }
    }

    private static function assertByte(
        string $name,
        int $value,
    ): void {
        if (
            0 > $value
            || 255 < $value
        ) {
            throw new InvalidRawFitMessage(sprintf('%s must fit into one byte.', $name));
        }
    }

    public function sequence(): int
    {
        return $this->sequenceNumber;
    }

    public function byteOffset(): int
    {
        return $this->messageByteOffset;
    }

    public function recordHeaderByte(): int
    {
        return $this->recordHeaderByte;
    }
}
